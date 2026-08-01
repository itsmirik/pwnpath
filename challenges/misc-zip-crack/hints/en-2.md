Extract the hash with `zip2john secret.zip > hash`, then run
`john --wordlist=rockyou.txt hash` (or `hashcat -m 17225/17200`). Unzip with the
recovered password to reveal an image containing the flag.
