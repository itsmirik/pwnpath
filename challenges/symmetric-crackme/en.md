A login binary greets you with an "Immersive Cybersecurity Experience" and asks for a password. Get it right and it prints `Success!`.

The program reads a fixed-length password, runs it through a reversible (XOR-style) transform, and compares the result against an embedded target with `memcmp`. Because the transform is symmetric, there are several ways in: static analysis in a disassembler, or the classic `LD_PRELOAD` trick to intercept `memcmp` and read off both operands. The password itself is the flag.

You are given: `rev` (a 64-bit Linux ELF, not stripped).
