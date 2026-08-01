"I love assembly so much, I decided to make my own!" The author built a tiny stack-based virtual machine in Python and compiled a flag-checker into bytecode for it.

`vm.py` is the interpreter (with deliberately unhelpful single-letter names) and `instructions.bin` is the compiled program it runs. The first byte of the bytecode is the flag length; the rest are opcodes that push your input, do arithmetic/XOR, and assert the result is zero. Recover the opcode semantics and work backwards to the flag.

You are given: `vm.py`, `instructions.bin`.
