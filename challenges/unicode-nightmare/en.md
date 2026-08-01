This one is the result of many hours of geeking out about Unicode. The Python script asks for a flag and answers `Success!` or `Incorrect`.

Almost nothing is what it looks like: variables are written with exotic italic/bold Unicode letters, digits come from non-ASCII numeral systems fed into `int()`, and the real logic is hidden inside layered `base64`/`base32` blobs passed to `exec`. Peel back the layers, resolve the odd numerals, and you will find a simple per-character check that reveals the flag.

You are given: `bad.py`.
