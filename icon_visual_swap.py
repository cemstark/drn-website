import os

base_dir = r"c:\Users\NAZLICAN\Desktop\drn-website"
targets = ["index.html", "hizmetler.html"]

for file in targets:
    path = os.path.join(base_dir, file)
    if not os.path.exists(path):
        continue
    
    with open(path, "r", encoding="utf-8") as f:
        content = f.read()

    # 1. Bakım görseli -> drn-dyno.jpeg (Rot-Balance'ın resmi) olacak, Rot-balance görseli silinecek
    # In both index & hizmetler, BAKIM card uses drn-atolye.jpeg?v=20260328
    # But wait, to be safe, let's just replace the exact block.
    
    # Let's find Bakim's image wrap
    if "alt=\"Araç Bakım\"" in content:
        content = content.replace('src="images/drn-atolye.jpeg?v=20260328" alt="Araç Bakım"', 'src="images/drn-dyno.jpeg?v=20260328" alt="Araç Bakım"')

    # 2. PDR Ekipmanları görseli -> drn-ekipman2.jpeg (Ekspertiz'in resmi) olacak
    if "alt=\"Elektrik\"" in content:
        content = content.replace('src="images/drn-ekipman1.jpeg?v=20260328" alt="Elektrik"', 'src="images/drn-ekipman2.jpeg?v=20260328" alt="PDR Ekipmanları"')
    
    # 3. Aktif Atölye (PDR) -> Sadece "Aktif Atölye" yazsın
    content = content.replace("<h3>Aktif Atölye (PDR)</h3>", "<h3>Aktif Atölye</h3>")

    # 4. Rot Balance görseli tamamen silinip sadece ikon kalsın
    # Remove:
    # <div class="service-detail-img-wrap">
    #   <img src="images/drn-dyno.jpeg?v=20260328" alt="Rot Balance" class="service-detail-img" loading="lazy">
    # </div>
    # EXACT:
    dyno_wrap = """<div class="service-detail-img-wrap">
            <img src="images/drn-dyno.jpeg?v=20260328" alt="Rot Balance" class="service-detail-img" loading="lazy">
          </div>"""
    content = content.replace(dyno_wrap, "")
    
    # The alt might be different in different files, let's just remove based on alt
    dyno_wrap_alt = """<div class="service-detail-img-wrap">\n            <img src="images/drn-dyno.jpeg?v=20260328" alt="Rot Balance" class="service-detail-img" loading="lazy">\n          </div>"""
    content = content.replace(dyno_wrap_alt, "")

    # For index.html, it might have whitespace variations, so doing a clean replace:
    import re
    # Remove dyno wrapper
    content = re.sub(
        r'<div class="service-detail-img-wrap">\s*<img src="images/drn-dyno\.jpeg\?v=20260328"[^>]*>\s*</div>',
        '', content)

    # 5. Ekspertiz takımları görseli kaldırılacak (drn-ekipman2.jpeg)
    content = re.sub(
        r'<div class="service-detail-img-wrap">\s*<img src="images/drn-ekipman2\.jpeg\?v=20260328" alt="Ekspertiz"[^>]*>\s*</div>',
        '', content)

    # 6. Mekanik görsel kaldırılacak (drn-atolye3.jpeg)
    content = re.sub(
        r'<div class="service-detail-img-wrap">\s*<img src="images/drn-atolye3\.jpeg\?v=20260328" alt="Mekanik"[^>]*>\s*</div>',
        '', content)

    with open(path, "w", encoding="utf-8") as f:
        f.write(content)

print("Visual adjustments complete.")
