Someone ran a flag-checking script through a JavaScript obfuscator. The result is a wall of hex-named variables and string-array lookups that is deliberately hard to read.

Underneath the noise, the pieces of the flag are still there as an array of strings. Untangle the indirection (or just evaluate the string array in a console) and reassemble the flag.

You are given: `obfuscJStor.js`.
