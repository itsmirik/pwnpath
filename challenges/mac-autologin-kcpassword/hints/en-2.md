The password is simply XORed with a fixed, publicly known static key. XOR the file bytes with that same key to get the password back (stop at the byte that matches the key — that's the padding).
