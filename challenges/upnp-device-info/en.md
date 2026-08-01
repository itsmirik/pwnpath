While poking at a router's exposed UPnP service, an investigator dumped the raw response from its `GetDeviceInfo` action. It looks like meaningless bytes, but it is actually structured data.

The blob is encoded in the Wi-Fi Protected Setup (WPS / Wi-Fi Simple Config) TLV format: a stream of `type(2 bytes) length(2 bytes) value` records. Parse it, find the field carrying the device `Nonce`, and base64-encode that value.

Flag format: `byuctf{base64value}` — the flag is the literal base64-encoded nonce inside the braces.

You are given: `msg.bin` (the raw GetDeviceInfo response) and `actions.txt` (the list of UPnP actions, useful for identifying the service).
