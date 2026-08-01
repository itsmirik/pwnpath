# RSA: Common Prime

Two separate RSA messages were intercepted. Each has its own modulus
(`n1`, `n2`), exponent and ciphertext - all provided in `rsa.txt`.

Individually, each 2048-bit modulus would be impossible to factor. But the two
keys were generated carelessly, and that's the whole point of this challenge.
Decrypt either message to get the flag.

Flag format: `byuctf{...}`
