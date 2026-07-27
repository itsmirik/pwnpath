# HackTepa — Release Challenge Set (20)

> ⚠️ **INTERNAL / SPOILERS.** This file contains every flag and full solution.
> Do **not** commit to a public repo or post it. Only the *prompts* go to LinkedIn.

All 20 challenges were built and **solved end-to-end with real tools** before being
seeded (`grep`, `unzip`, `gunzip`, OpenCV QR decode, Fermat factorization, HMAC brute,
Brainfuck interpreter, EXIF read, zero-width extraction, etc.). Every `static_flag`
was verified to pass through the live `App\Services\FlagGenerator::matches()` pipeline.

- **Categories:** web, crypto, forensics, osint, llm, misc (all 6 covered)
- **Difficulty split:** 7 easy · 8 medium · 5 hard
- **Points:** easy 100 · medium 300 · hard 700 → **6 500 pts total**
- **Flag style:** readable `HTP{...}` (static), format hint `HTP\{[a-zA-Z0-9_]+\}`
- **Seeder:** `database/seeders/ChallengeSeeder.php`
- **Assets:** `database/seeders/assets/challenges/<slug>/` → copied to the `challenges` disk on seed

> **Heads-up:** three *legacy demo* challenges (`cookie-monster`, `caesars-ghost`,
> `context-leak`) still exist from earlier work. They use **dynamic** flags with
> **placeholder** files and are **not solvable** — do **not** post them. Archive or
> finish them before release.

---

## Summary table

| #  | Title           | Slug              | Category  | Diff   | Pts | Flag                             |
|----|-----------------|-------------------|-----------|--------|-----|----------------------------------|
| 1  | Matryoshka      | `matryoshka`      | crypto    | easy   | 100 | `HTP{layers_all_the_way_down}`   |
| 2  | Single Malt     | `single-malt`     | crypto    | medium | 300 | `HTP{x0r_1s_not_encryption}`     |
| 3  | Weak RSA        | `weak-rsa`        | crypto    | medium | 300 | `HTP{sh0r_would_be_proud}`       |
| 4  | Repeat Offender | `repeat-offender` | crypto    | hard   | 700 | `HTP{fr3quency_analysis_wins}`   |
| 5  | Needle          | `needle`          | forensics | easy   | 100 | `HTP{gr3p_is_your_friend}`       |
| 6  | Snapshot        | `snapshot`        | forensics | easy   | 100 | `HTP{m3tadata_never_lies}`       |
| 7  | Polyglot        | `polyglot`        | forensics | medium | 300 | `HTP{h1dden_in_plain_bytes}`     |
| 8  | Deep Freeze     | `deep-freeze`     | forensics | hard   | 700 | `HTP{russian_dolls_of_zlib}`     |
| 9  | Crumbs          | `crumbs`          | web       | easy   | 100 | `HTP{c00kies_are_not_secure}`    |
| 10 | Weak Signature  | `weak-jwt`        | web       | medium | 300 | `HTP{weak_hs256_secret}`         |
| 11 | Source Sleuth   | `source-sleuth`   | web       | medium | 300 | `HTP{cl1ent_side_checks_lol}`    |
| 12 | Forged in Fire  | `forged-in-fire`  | web       | hard   | 700 | `HTP{type_juggl1ng_php}`         |
| 13 | Whisper         | `whisper`         | llm       | easy   | 100 | `HTP{pr0mpt_says_no_but_base64}` |
| 14 | Context Bleed   | `context-bleed`   | llm       | medium | 300 | `HTP{r3trieval_augmented_leak}`  |
| 15 | Tokenizer Trap  | `tokenizer-trap`  | llm       | hard   | 700 | `HTP{z3ro_width_smuggling}`      |
| 16 | Geotag          | `geotag`          | osint     | easy   | 100 | `HTP{registan_samarkand}`        |
| 17 | Handle Hunt     | `handle-hunt`     | osint     | medium | 300 | `HTP{osint_is_just_reading}`     |
| 18 | QR Quest        | `qr-quest`        | misc      | easy   | 100 | `HTP{scan_me_maybe}`             |
| 19 | Brainrot        | `brainrot`        | misc      | medium | 300 | `HTP{turing_tarpit_escape}`      |
| 20 | Esoterica       | `esoterica`       | misc      | hard   | 700 | `HTP{decode_decompile_deflate}`  |

---

## CRYPTO

### 1. Matryoshka · crypto · easy · 100

**Files:** `cipher.txt`
**Prompt:** Peel several layers of common encodings until printable text falls out.
**Solve:**

1. `cipher.txt` is Base64. Decode → a Base32 string (A–Z, 2–7).
2. Base32-decode → another Base64 string.
3. Base64-decode → the flag.

```bash
cat cipher.txt | base64 -d | base32 -d | base64 -d
```

```python
import base64
print(base64.b64decode(base64.b32decode(base64.b64decode(open("cipher.txt").read().strip()))).decode())
```

**Flag:** `HTP{layers_all_the_way_down}`

### 2. Single Malt · crypto · medium · 300

**Files:** `cipher.hex`
**Prompt:** Flag XOR-ed with one repeating byte. 256 possibilities.
**Solve:** hex-decode, brute the key 0x00–0xFF, keep the one that yields `HTP{...}`.

```python
ct = bytes.fromhex(open("cipher.hex").read().strip())
for k in range(256):
    p = bytes(b ^ k for b in ct)
    if p.startswith(b"HTP{") and p.endswith(b"}"):
        print(k, p.decode())     # key = 0x5c
```

**Flag:** `HTP{x0r_1s_not_encryption}`

### 3. Weak RSA · crypto · medium · 300

**Files:** `key.txt` (`n`, `e`, `c`)
**Prompt:** Textbook RSA where the two primes are very close together.
**Solve:** the closeness makes **Fermat factorization** trivial.

```python
import math
n,e,c = ...  # from key.txt
a = math.isqrt(n) + 1
while True:
    b2 = a*a - n
    b = math.isqrt(b2)
    if b*b == b2: break
    a += 1
p,q = a-b, a+b
d = pow(e, -1, (p-1)*(q-1))
m = pow(c, d, n)
print(m.to_bytes((m.bit_length()+7)//8, "big").decode())
```

**Flag:** `HTP{sh0r_would_be_proud}`

### 4. Repeat Offender · crypto · hard · 700

**Files:** `cipher.bin`
**Prompt:** English prose encrypted with repeating-key XOR; flag is inside the plaintext.
**Solve (classic Vigenère/XOR cryptanalysis):**

1. **Key length** via normalized Hamming distance (or Kasiski) — the minimum-distance
   length wins. (Key here is 8 bytes: `hacktepa`.)
2. Transpose ciphertext into `keylen` columns; each column is a **single-byte XOR** —
   solve each by English letter-frequency scoring.
3. Reassemble; read the flag out of the recovered text.

```python
ct = open("cipher.bin","rb").read()
key = b"hacktepa"   # recovered by the steps above
pt = bytes(ct[i]^key[i%len(key)] for i in range(len(ct))).decode()
print(pt[pt.index("HTP{"):pt.index("}")+1])
```

**Flag:** `HTP{fr3quency_analysis_wins}`

---

## FORENSICS

### 5. Needle · forensics · easy · 100

**Files:** `dump.log` (4 000 noise lines)
**Solve:** one regex.

```bash
grep -o 'HTP{[^}]*}' dump.log
```

**Flag:** `HTP{gr3p_is_your_friend}`

### 6. Snapshot · forensics · easy · 100

**Files:** `snapshot.jpg`
**Solve:** the flag is in EXIF (`UserComment`, `Artist=HackTepa`).

```bash
exiftool snapshot.jpg          # or: strings snapshot.jpg | grep HTP
```

**Flag:** `HTP{m3tadata_never_lies}`

### 7. Polyglot · forensics · medium · 300

**Files:** `artwork.png` (valid image **+** appended ZIP)
**Solve:** ZIP reads its directory from the end of the file, so a valid PNG can hide an archive.

```bash
unzip artwork.png          # or: binwalk -e artwork.png  → flag.txt
```

**Flag:** `HTP{h1dden_in_plain_bytes}`

### 8. Deep Freeze · forensics · hard · 700

**Files:** `payload.bin` (gzip × 3)
**Solve:** decompress until the `1F 8B` gzip magic is gone.

```bash
cat payload.bin | gunzip | gunzip | gunzip
```

**Flag:** `HTP{russian_dolls_of_zlib}`

---

## WEB

### 9. Crumbs · web · easy · 100

**Files:** `request.txt` (captured HTTP request)
**Solve:** the `session` cookie is Base64URL → JSON. Decode the `note` field.

```python
import base64, json
v = "...session value..."
print(json.loads(base64.urlsafe_b64decode(v + "="*(-len(v)%4)))["note"])
```

**Flag:** `HTP{c00kies_are_not_secure}`

### 10. Weak Signature · web · medium · 300

**Files:** `token.txt` (JWT, HS256), `wordlist.txt`
**Solve:** HS256 is symmetric — brute the signing secret from the wordlist; the secret **is** the flag body.

```python
import base64, hmac, hashlib
h,b,s = open("token.txt").read().strip().split(".")
for w in open("wordlist.txt").read().split():
    sig = base64.urlsafe_b64encode(hmac.new(w.encode(), f"{h}.{b}".encode(), hashlib.sha256).digest()).rstrip(b"=").decode()
    if sig == s:
        print("HTP{"+w+"}")     # secret = weak_hs256_secret
```

(`jwt_tool -C -d wordlist.txt <token>` works too.)
**Flag:** `HTP{weak_hs256_secret}`

### 11. Source Sleuth · web · medium · 300

**Files:** `login.js`
**Solve:** the "password" is rebuilt client-side from an obfuscated array (reverse it, subtract 7 from each code). Don't compare — just
print the target.

```python
obf = [...]        # the _k array from login.js
print("".join(chr(x-7) for x in obf[::-1]))
```

Or in a browser console: run the reconstruction and `console.log` the string instead of `check()`.
**Flag:** `HTP{cl1ent_side_checks_lol}`

### 12. Forged in Fire · web · hard · 700

**Files:** `admin.php`
**Prompt:** `md5($_GET['token']) == '0e4620974319065090195629...'` — PHP loose comparison.
**Solve:** the stored hash is a **magic hash** (`0e` + all digits → PHP reads it as float `0`).
Supply any input whose md5 is also `0e<all digits>`; the famous one is `240610708`
(`md5 = 0e462097431906509019562988736854`). Then `0 == 0` bypasses auth.
Understanding the type-juggling *is* the challenge; the reward flag:
**Flag:** `HTP{type_juggl1ng_php}`

---

## LLM

### 13. Whisper · llm · easy · 100

**Files:** `system_prompt.txt`
**Solve:** the "never reveal" key is sitting in the prompt, Base64-encoded.

```bash
echo '<blob>' | base64 -d
```

**Flag:** `HTP{pr0mpt_says_no_but_base64}`

### 14. Context Bleed · llm · medium · 300

**Files:** `chat.log`
**Solve:** the RAG bot refuses the token whole but leaks one chunk per document summary
(between ellipses). Concatenate the three chunks in order, wrap in `HTP{...}`.
**Flag:** `HTP{r3trieval_augmented_leak}`

### 15. Tokenizer Trap · llm · hard · 700

**Files:** `transcript.txt`
**Solve:** the visible reply hides **zero-width** chars. `U+200B` (ZWSP)=0, `U+200C` (ZWNJ)=1.
Filter to zero-width chars, map to bits, group into bytes, ASCII-decode.

```python
t = open("transcript.txt", encoding="utf-8").read()
bits = "".join("1" if c=="‌" else "0" for c in t if c in "​‌")
print(bytes(int(bits[i:i+8],2) for i in range(0,len(bits),8)).decode())
```

**Flag:** `HTP{z3ro_width_smuggling}`

---

## OSINT

### 16. Geotag · osint · easy · 100

**Files:** `photo.jpg`
**Solve:** read EXIF GPS → `39.6547 N, 66.9758 E` → reverse-geocode → the **Registan** square in **Samarkand**, Uzbekistan (UNESCO site).
Flag = `HTP{<landmark>_<city>}`.

```bash
exiftool -gpslatitude -gpslongitude photo.jpg
```

**Flag:** `HTP{registan_samarkand}`

### 17. Handle Hunt · osint · medium · 300

**Files:** `profile.html`, `readme.txt`, `tag3_just_reading.dat`
**Solve:** three fragments, no external sites needed.

1. `profile.html` — HTML comment `build-tag:` is Base64 → `osint`.
2. `readme.txt` — `tag2=` is ROT13 → `is`.
3. `.dat` **filename** → `just_reading`.
   Join with underscores: `HTP{osint_is_just_reading}`.

```bash
grep -o 'build-tag: [^ ]*' profile.html | cut -d' ' -f2 | base64 -d   # osint
grep tag2 readme.txt | tr 'A-Za-z' 'N-ZA-Mn-za-m'                       # is
```

**Flag:** `HTP{osint_is_just_reading}`

---

## MISC

### 18. QR Quest · misc · easy · 100

**Files:** `code.png`
**Solve:** scan it (phone camera, `zbarimg code.png`, or OpenCV `QRCodeDetector`).
Verified decode → the flag.
**Flag:** `HTP{scan_me_maybe}`

### 19. Brainrot · misc · medium · 300

**Files:** `program.bf`
**Solve:** run the Brainfuck program; stdout is the flag (no input needed). Any interpreter works.
**Flag:** `HTP{turing_tarpit_escape}`

### 20. Esoterica · misc · hard · 700

**Files:** `stage1.txt`
**Prompt:** built as `flag → zlib deflate → Base64 → reverse string`. Undo in reverse.

```python
import base64, zlib
s = open("stage1.txt").read().strip()
print(zlib.decompress(base64.b64decode(s[::-1])).decode())
```

**Flag:** `HTP{decode_decompile_deflate}`

---

## How to (re)seed

```bash
# inside the app container (DB host is the docker service `pgsql`)
docker exec hacktepa-laravel.test-1 php artisan db:seed --class=ChallengeSeeder
```

Idempotent (`updateOrCreate` by slug); assets are copied from
`database/seeders/assets/challenges/` onto the `challenges` disk each run.

## Suggested LinkedIn rollout

Post the **prompts only** (title + category + difficulty + the file), never this file.
Good teaser order — one easy from each category first to hook a wide audience, then
ramp difficulty over the week:
`needle → crumbs → qr-quest → matryoshka → whisper → geotag` (day-1 easies) →
mediums → finish with the hards (`repeat-offender`, `deep-freeze`, `forged-in-fire`,
`tokenizer-trap`, `esoterica`).
