# NTLM

We dumped the SAM, SYSTEM, and SECURITY hives from a Windows machine and pulled
out this NTLM hash (`hash.txt`):

```
35505F45B250AC730941E8FED9C6EF14
```

A sticky note said the password is only **4 characters** long. Crack it.

Flag format: `ctf{password}`
