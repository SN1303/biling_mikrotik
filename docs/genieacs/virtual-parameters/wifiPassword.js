// VirtualParameter: wifiPassword
// Password WiFi 2,4 GHz. Kosong bila ONT menyembunyikannya; aplikasi lalu memakai VirtualParameters.WlanPassword.
// Dipakai aplikasi billing (halaman ONT pelanggan). Pasang lewat Admin → Virtual Parameters dengan nama "wifiPassword".
const JALUR = [
  "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase",
  "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase",
  "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey",
  "Device.WiFi.AccessPoint.1.Security.KeyPassphrase",
];

// Kesegaran data mengikuti yang diminta pemanggil (args[0]): provision "default" (per jam) atau
// tombol Refresh di billing (saat itu juga). Tanpa permintaan, pakai nilai yang sudah tersimpan.
const SEGAR = (args[0] && args[0].value) || 1;

// Baca: nilai pertama yang tidak kosong.
function baca(jalur) {
  for (const p of jalur) {
    const d = declare(p, {value: SEGAR});
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
