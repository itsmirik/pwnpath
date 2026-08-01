Router'ning ochiq UPnP xizmatini o'rganayotib, tekshiruvchi uning `GetDeviceInfo` amalidan xom javobni oldi. U ma'nosiz baytlarga o'xshaydi, lekin aslida bu tuzilgan ma'lumot.

Blok Wi-Fi Protected Setup (WPS / Wi-Fi Simple Config) TLV formatida kodlangan: `tur(2 bayt) uzunlik(2 bayt) qiymat` yozuvlari oqimi. Uni tahlil qiling, qurilma `Nonce` maydonini toping va o'sha qiymatni base64 ga kodlang.

Flag formati: `byuctf{base64value}` — flag qavs ichidagi base64 kodlangan nonce'ning o'zi.

Sizga berilgan fayllar: `msg.bin` (GetDeviceInfo xom javobi) va `actions.txt` (UPnP amallari ro'yxati, xizmatni aniqlashga yordam beradi).
