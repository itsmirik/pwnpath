# RSA: Broadcast

The same secret message was encrypted and sent to three different recipients.
Each recipient has their own modulus (`n1`, `n2`, `n3`) and ciphertext
(`c1`, `c2`, `c3`), but they all share the same small public exponent `e = 3`.

All parameters are in `rsa.txt`. Recover the original message.

Flag format: `byuctf{...}`
