import subprocess
import re
import os
import json
import time

import modules.ap as ap
import config

def get_wireless_client_metrics(interface):
    """
    Parse 'iw dev <interface> station dump' output and extract per-MAC metrics.
    Returns a dict mapping MAC address (uppercase) to a metrics dict with:
    - signal_dbm: signal strength in dBm (int or None)
    - connected_seconds: connection duration in seconds (int or None)
    - inactive_ms: inactive time in milliseconds (int or None)
    - tx_bytes, rx_bytes, tx_packets, rx_packets: traffic stats
    """
    metrics = {}
    
    try:
        station_dump = subprocess.run(
            ["iw", "dev", interface, "station", "dump"],
            capture_output=True,
            text=True,
            timeout=5
        )
    except (subprocess.TimeoutExpired, FileNotFoundError):
        return metrics
    
    current_mac = None
    
    for line in station_dump.stdout.splitlines():
        # Parse station header: "Station aa:bb:cc:dd:ee:ff (on wlan0)"
        if line.startswith("Station "):
            parts = line.split()
            if len(parts) >= 2:
                current_mac = parts[1].strip().upper()
                if len(current_mac) == 17 and current_mac.count(":") == 5:
                    metrics[current_mac] = {
                        "signal_dbm": None,
                        "connected_seconds": None,
                        "inactive_ms": None,
                        "tx_bytes": 0,
                        "rx_bytes": 0,
                        "tx_packets": 0,
                        "rx_packets": 0
                    }
                else:
                    current_mac = None
        elif current_mac and current_mac in metrics:
            line = line.strip()
            # Parse tab-indented metric lines
            if line.startswith("signal:"):
                # "signal: -45 dBm"
                match = re.search(r'(-?\d+)\s+dBm', line)
                if match:
                    metrics[current_mac]["signal_dbm"] = int(match.group(1))
            elif line.startswith("connected time:"):
                # "connected time: 3600 seconds"
                match = re.search(r'(\d+)\s+seconds', line)
                if match:
                    metrics[current_mac]["connected_seconds"] = int(match.group(1))
            elif line.startswith("inactive time:"):
                # "inactive time: 4200 ms"
                match = re.search(r'(\d+)\s+ms', line)
                if match:
                    metrics[current_mac]["inactive_ms"] = int(match.group(1))
            elif line.startswith("tx bytes:"):
                match = re.search(r':\s*(\d+)', line)
                if match:
                    metrics[current_mac]["tx_bytes"] = int(match.group(1))
            elif line.startswith("rx bytes:"):
                match = re.search(r':\s*(\d+)', line)
                if match:
                    metrics[current_mac]["rx_bytes"] = int(match.group(1))
            elif line.startswith("tx packets:"):
                match = re.search(r':\s*(\d+)', line)
                if match:
                    metrics[current_mac]["tx_packets"] = int(match.group(1))
            elif line.startswith("rx packets:"):
                match = re.search(r':\s*(\d+)', line)
                if match:
                    metrics[current_mac]["rx_packets"] = int(match.group(1))
    
    return metrics

def get_active_wireless_clients_mac(interface):
    station_dump = subprocess.run(["iw", "dev", interface, "station", "dump"], capture_output=True, text=True)

    macs = []
    for line in station_dump.stdout.splitlines():
        if line.startswith("Station "):
            # Typical format: Station aa:bb:cc:dd:ee:ff (on wlan0)
            parts = line.split()
            if len(parts) >= 2:
                mac = parts[1].strip()
                # Optional: basic validation
                if len(mac) == 17 and mac.count(":") == 5:
                    macs.append(mac.upper())

    return macs

def get_active_wireless_clients_amount():
    ap_interface = ap.interface()
    macs = get_active_wireless_clients_mac(ap_interface)

    return len(macs)

def get_active_ethernet_clients_mac():
    arp_macs = []

    arp_output = subprocess.run(['ip', 'neigh', 'show'], capture_output=True, text=True)
    if arp_output.stdout:
        for line in arp_output.stdout.splitlines():
            line = line.strip()
            if not line:
                continue

            # Matches lines like:
            # 192.168.100.45 dev enp3s0 lladdr 3c:97:0e:12:34:56 REACHABLE
            # 192.168.1.120 dev eth0 lladdr 00:1a:2b:3c:4d:5e DELAY
            match = re.match(
                r'^(\S+)\s+dev\s+(eth[0-9]+|en\w+)\s+lladdr\s+(\S+)\s+(REACHABLE|DELAY|PROBE)',
                line
            )
            if match:
                mac = match.group(3).upper()
                arp_macs.append(mac)

    lease_macs = []

    if os.path.isfile(config.DNSMASQ_LEASES):
        try:
            with open(config.DNSMASQ_LEASES, encoding="utf-8", errors="ignore") as f:
                for line in f:
                    line = line.strip()
                    if not line or line.startswith("#"):
                        continue
                    fields = line.split()
                    if len(fields) >= 3:
                        # format: expiry MAC IP hostname ...
                        mac = fields[1].upper()
                        lease_macs.append(mac)
        except Exception:
            pass

    active_ethernet_macs = []
    for mac in arp_macs:
        if mac in lease_macs and mac not in active_ethernet_macs:
            active_ethernet_macs.append(mac)


    return active_ethernet_macs

def get_active_ethernet_clients_amount():
    eth_macs = get_active_ethernet_clients_mac()
    return len(eth_macs)

def get_active_clients_amount():
    wireless_clients_count = get_active_wireless_clients_amount()
    ethernet_clients_count = get_active_ethernet_clients_amount()

    return wireless_clients_count + ethernet_clients_count

def get_active_clients():
    ap_interface = ap.interface()
    wireless_macs = get_active_wireless_clients_mac(ap_interface)
    ethernet_macs = get_active_ethernet_clients_mac()
    
    # Get wireless metrics (signal, connected time, etc.)
    wireless_metrics = get_wireless_client_metrics(ap_interface)
    
    # Import vendor lookup
    try:
        from modules import oui
        has_oui = True
    except ImportError:
        has_oui = False

    arp_output = subprocess.run(['arp', '-i', ap_interface], capture_output=True, text=True)
    arp_mac_addresses = set(line.split()[2] for line in arp_output.stdout.splitlines()[1:])

    dnsmasq_output = subprocess.run(['cat', config.DNSMASQ_LEASES], capture_output=True, text=True)
    active_clients = []

    for line in dnsmasq_output.stdout.splitlines():
        fields = line.split()
        mac_address = fields[1]

        if mac_address in arp_mac_addresses:
            normalized_mac = mac_address.upper()
            is_wireless = True if normalized_mac in wireless_macs else False
            is_ethernet = True if normalized_mac in ethernet_macs else False
            
            # Calculate connected duration (current time - lease expiry time, approximate)
            lease_expiry = int(fields[0])
            connected_seconds = None
            if is_wireless and normalized_mac in wireless_metrics:
                connected_seconds = wireless_metrics[normalized_mac].get("connected_seconds")

            client_data = {
                "timestamp": int(fields[0]),
                "mac_address": fields[1],
                "ip_address": fields[2],
                "hostname": fields[3],
                "client_id": fields[4],
                "connection_type": 'wireless' if is_wireless else ('ethernet' if is_ethernet else 'unknown')
            }
            
            # Add wireless-specific enrichment
            if is_wireless and normalized_mac in wireless_metrics:
                metrics = wireless_metrics[normalized_mac]
                client_data["signal_dbm"] = metrics.get("signal_dbm")
                client_data["connected_seconds"] = metrics.get("connected_seconds")
                client_data["inactive_ms"] = metrics.get("inactive_ms")
                client_data["tx_bytes"] = metrics.get("tx_bytes", 0)
                client_data["rx_bytes"] = metrics.get("rx_bytes", 0)
            
            # Add vendor lookup
            if has_oui:
                client_data["vendor"] = oui.get_vendor_name(mac_address)
            else:
                client_data["vendor"] = "Unknown"
            
            active_clients.append(client_data)

    json_output = json.dumps(active_clients, indent=2)
    return json_output

def get_active_clients_amount_by_interface(interface):
    arp_output = subprocess.run(['arp', '-i', interface], capture_output=True, text=True)
    mac_addresses = set(line.split()[2] for line in arp_output.stdout.splitlines()[1:])

    if mac_addresses:
        grep_pattern = '|'.join(mac_addresses)
        output = subprocess.run(['grep', '-iwE', grep_pattern, config.DNSMASQ_LEASES], capture_output=True, text=True)
        return len(output.stdout.splitlines())
    else:
        return 0

def get_active_clients_by_interface(interface):
    arp_output = subprocess.run(['arp', '-i', interface], capture_output=True, text=True)
    arp_mac_addresses = set(line.split()[2] for line in arp_output.stdout.splitlines()[1:])

    # Get wireless metrics if this is a wireless interface
    wireless_metrics = get_wireless_client_metrics(interface)
    
    # Import vendor lookup
    try:
        from modules import oui
        has_oui = True
    except ImportError:
        has_oui = False

    dnsmasq_output = subprocess.run(['cat', config.DNSMASQ_LEASES], capture_output=True, text=True)
    active_clients = []

    for line in dnsmasq_output.stdout.splitlines():
        fields = line.split()
        mac_address = fields[1]

        if mac_address in arp_mac_addresses:
            normalized_mac = mac_address.upper()
            
            client_data = {
                "timestamp": int(fields[0]),
                "mac_address": fields[1],
                "ip_address": fields[2],
                "hostname": fields[3],
                "client_id": fields[4],
            }
            
            # Add wireless-specific enrichment if available
            if normalized_mac in wireless_metrics:
                metrics = wireless_metrics[normalized_mac]
                client_data["signal_dbm"] = metrics.get("signal_dbm")
                client_data["connected_seconds"] = metrics.get("connected_seconds")
                client_data["inactive_ms"] = metrics.get("inactive_ms")
                client_data["tx_bytes"] = metrics.get("tx_bytes", 0)
                client_data["rx_bytes"] = metrics.get("rx_bytes", 0)
            
            # Add vendor lookup
            if has_oui:
                client_data["vendor"] = oui.get_vendor_name(mac_address)
            else:
                client_data["vendor"] = "Unknown"
            
            active_clients.append(client_data)

    json_output = json.dumps(active_clients, indent=2)
    return json_output

