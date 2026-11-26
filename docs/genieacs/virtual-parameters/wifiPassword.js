// VirtualParameter: wifiPassword
// Password WiFi 2,4 GHz. Kosong bila ONT menyembunyikannya; aplikasi lalu memakai VirtualParameters.WlanPassword.
// Dipakai aplikasi billing (halaman ONT pelanggan). Pasang lewat Admin → Virtual Parameters dengan nama "wifiPassword".
const JALUR = [
  "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase",
  "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase",
  "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey",
  "Device.WiFi.AccessPoint.1.Security.KeyPassphrase",
];

// Baca: nilai pertama yang tidak kosong. Date.now() memaksa ONT ditanya ulang setiap kali dievaluasi.
function baca(jalur) {
  for (const p of jalur) {
    const d = declare(p, {value: Date.now()});
    if (d.size && d.value && d.value[0] !== undefined && d.value[0] !== null && d.value[0] !== "") return String(d.value[0]);
  }
  return "";
}
// Tulis hanya ke jalur yang memang ada di ONT, supaya tidak memicu fault.
function tulis(jalur, nilai) {
  for (const p of jalur) {
    if (declare(p, {value: 1}).size) declare(p, null, {value: nilai});
  }
}

let v = "";
if (args[1] && args[1].value) {
  v = String(args[1].value[0]);
  tulis(JALUR, v);
  // Salinan yang dibaca VirtualParameters.WlanPassword saat ONT menyembunyikan password
  tulis(["InternetGatewayDevice.DeviceInfo.X_CT-COM_CardKey"], v);
} else {
  v = baca(JALUR);
}

return {writable: true, value: [v, "xsd:string"]};
