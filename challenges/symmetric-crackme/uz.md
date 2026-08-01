Kirish binari sizni "Immersive Cybersecurity Experience" bilan kutib oladi va parol so'raydi. To'g'ri kiriting — u `Success!` chiqaradi.

Dastur belgilangan uzunlikdagi parolni o'qiydi, uni qaytariladigan (XOR uslubidagi) o'zgartirishdan o'tkazadi va natijani `memcmp` orqali ichki maqsad bilan taqqoslaydi. O'zgartirish simmetrik bo'lgani uchun bir necha yo'l bor: disassemblerda statik tahlil yoki `memcmp`ni ushlab, ikkala argumentni o'qish uchun klassik `LD_PRELOAD` hiylasi. Parolning o'zi flagdir.

Sizga berilgan fayl: `rev` (64-bitli Linux ELF, stripped emas).
