import { initDevicesAjax } from "../ajax/devices.js";

export function initDevices() {
    console.info("RaspAP Devices UI module initialized");
    initDevicesAjax();
}
