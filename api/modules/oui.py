"""
OUI (Organizationally Unique Identifier) vendor lookup utility.
Maps MAC address prefixes to vendor names.
"""

import os

# Built-in top-50 vendors by market share for quick lookup
BUILTIN_VENDORS = {
    "00:04:96": "Cisco",
    "00:05:02": "Apple",
    "00:05:87": "Cisco",
    "00:07:01": "Cisco",
    "00:08:74": "3Com",
    "00:0A:95": "Cisco",
    "00:0B:85": "Cisco",
    "00:0E:1B": "Cisco",
    "00:0F:34": "Netgear",
    "00:10:18": "Cisco",
    "00:11:2F": "Cisco",
    "00:13:10": "Cisco",
    "00:13:20": "Cisco",
    "00:15:C5": "Cisco",
    "00:17:F2": "Cisco",
    "00:1A:2F": "Cisco",
    "00:1A:6F": "Cisco",
    "00:1B:0D": "Cisco",
    "00:1C:58": "Cisco",
    "00:1D:45": "Cisco",
    "00:1E:F7": "Cisco",
    "00:1F:CA": "Cisco",
    "00:22:55": "Cisco",
    "00:24:AB": "Cisco",
    "00:25:86": "Cisco",
    "00:27:0D": "Cisco",
    "2C:F0:5D": "Samsung",
    "30:5A:3A": "Apple",
    "34:15:9C": "TP-Link",
    "38:60:77": "Huawei",
    "40:A8:F7": "Apple",
    "44:F4:6D": "LG",
    "48:E2:30": "Raspberry Pi",
    "50:E5:49": "Netgear",
    "54:52:00": "Cisco",
    "54:72:4E": "Cisco",
    "58:1F:AA": "Apple",
    "5C:AA:BC": "Apple",
    "60:03:08": "Philips",
    "64:76:BA": "Intel",
    "68:5B:35": "Samsung",
    "6C:96:CF": "Apple",
    "70:4D:7B": "Apple",
    "74:6D:FC": "ARRIS",
    "78:31:F1": "Ubiquiti",
    "7C:3B:E6": "Lenovo",
    "84:38:35": "Netgear",
    "88:5B:D6": "Cisco",
    "8C:FA:BA": "Asus",
    "90:18:87": "Netgear",
    "A0:21:95": "D-Link",
    "A4:12:69": "OnePlus",
    "A4:D1:D2": "TP-Link",
    "AC:BC:32": "Apple",
    "B0:35:9F": "TP-Link",
    "B8:27:EB": "Raspberry Pi",
    "B8:8A:60": "LG",
    "BC:5C:F3": "Harman",
    "C0:02:1A": "Cisco",
    "C0:11:73": "Linksys",
    "C0:A6:00": "Netgear",
    "C8:3A:35": "Asus",
    "D0:50:F2": "Harman",
    "D4:6E:0E": "Asus",
    "D8:5D:4C": "Apple",
    "DC:0B:34": "TP-Link",
    "E0:55:3D": "Sony",
    "E4:95:6E": "Amazon",
    "E8:9B:C5": "Netgear",
    "E8:FC:AF": "Linksys",
    "EC:1A:59": "LG",
    "F0:4D:A2": "Netgear",
    "F4:5C:89": "Apple",
    "F4:6D:04": "Cisco",
    "F8:B4:6A": "Netgear",
    "FC:3F:DB": "Arista",
    "FC:AA:14": "Apple",
}


def get_vendor_name(mac_address):
    """
    Lookup vendor name from MAC address.
    
    Args:
        mac_address: MAC address string (e.g. "AA:BB:CC:DD:EE:FF")
    
    Returns:
        Vendor name string or "Unknown" if not found.
    """
    if not mac_address or len(mac_address) < 8:
        return "Unknown"
    
    # Normalize to uppercase
    mac = mac_address.upper()
    
    # Extract first 3 octets (OUI prefix)
    oui_prefix = mac[:8]  # "AA:BB:CC"
    
    # Check built-in map first
    if oui_prefix in BUILTIN_VENDORS:
        return BUILTIN_VENDORS[oui_prefix]
    
    # Try to load from external OUI file if it exists
    # This can be extended later to load a full OUI database
    vendor = _load_from_oui_file(oui_prefix)
    if vendor:
        return vendor
    
    return "Unknown"


def _load_from_oui_file(oui_prefix):
    """
    Attempt to load vendor from external OUI file.
    File format expected: one line per entry, e.g. "AABBCC Vendor Name Inc."
    """
    try:
        # Look for OUI file in config directory
        oui_file = "/etc/raspap/oui.txt"
        
        if not os.path.isfile(oui_file):
            return None
        
        with open(oui_file, 'r', encoding='utf-8', errors='ignore') as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith('#'):
                    continue
                
                parts = line.split(None, 1)
                if len(parts) >= 2:
                    file_oui = parts[0].replace('-', ':')
                    if file_oui.upper() == oui_prefix:
                        return parts[1].strip()
    except Exception:
        pass
    
    return None
