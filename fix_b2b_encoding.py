from pathlib import Path
path = Path('src/b2b.php')
data = path.read_bytes()
print('read bytes', len(data))
try:
    decoded = data.decode('utf-8')
    print('utf8 decode ok, sample:', decoded[:200])
except Exception as e:
    print('utf8 decode failed:', e)
try:
    decoded_latin1 = data.decode('latin1')
    print('latin1 decode sample:', decoded_latin1[:200])
    redecoded = decoded_latin1.encode('latin1').decode('utf-8')
    print('redecoded sample:', redecoded[:200])
    # optionally write corrected text sample
    corrected = redecoded
    print('corrected sample second line:', corrected.splitlines()[9] if len(corrected.splitlines())>9 else 'none')
except Exception as e:
    print('latin1 decode failed:', e)
