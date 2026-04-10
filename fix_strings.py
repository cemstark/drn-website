import os
import re

html_files = [
    "index.html", "hakkimizda.html", "hizmetler.html", "galeri.html", 
    "iletisim.html", "blog.html", "sss.html", "randevu.html"
]

base_dir = r"c:\Users\NAZLICAN\Desktop\drn-website"

for file in html_files:
    file_path = os.path.join(base_dir, file)
    if not os.path.exists(file_path):
        continue
        
    with open(file_path, "r", encoding="utf-8") as f:
        content = f.read()
    
    # 1. Fix EKİNEKİN
    content = content.replace("EKİNEKİN OTO.", "EKİN OTO.")
    content = content.replace("DRNEKİN", "EKİN")
    
    # 2. Fix the WhatsApp URL
    content = content.replace('<a href="https://instagram.com" target="_blank" rel="noopener" class="social-link"><i class="fa-brands fa-whatsapp"></i></a>', '<a href="https://wa.me/905448500802" target="_blank" rel="noopener" class="social-link"><i class="fa-brands fa-whatsapp"></i></a>')

    with open(file_path, "w", encoding="utf-8") as f:
        f.write(content)

# Specific fixes for Galeri
galeri_path = os.path.join(base_dir, "galeri.html")
if os.path.exists(galeri_path):
    with open(galeri_path, "r", encoding="utf-8") as f:
        galeri = f.read()
    galeri = galeri.replace("<span>Boya Fırını</span>", "<span>Boya Fırını & Uygulama</span>")
    galeri = galeri.replace("<span>Kaporta</span>", "<span>Servis Ön Cephe</span>")
    galeri = galeri.replace("<span>Müşteri Danışma Ofisi</span>", "<span>Ofis & Bekleme Alanı</span>")
    with open(galeri_path, "w", encoding="utf-8") as f:
        f.write(galeri)

# Specific fixes for Hizmetler
hizmetler_path = os.path.join(base_dir, "hizmetler.html")
if os.path.exists(hizmetler_path):
    with open(hizmetler_path, "r", encoding="utf-8") as f:
        hz = f.read()
    
    # Add Sigorta Anlaşmalı
    if "Tüm sigorta şirketleriyle anlaşmamız" not in hz:
        hz = hz.replace(
            '<li class="service-feature-item"><i class="fa-solid fa-check"></i> Kaza hasarı kaporta onarımı</li>',
            '<li class="service-feature-item"><i class="fa-solid fa-file-contract" style="color:var(--red);"></i> Tüm sigorta şirketleriyle anlaşmamız bulunmaktadır.</li>\n              <li class="service-feature-item"><i class="fa-solid fa-check"></i> Kaza hasarı kaporta onarımı</li>'
        )
    
    # Titles
    hz = hz.replace('<h3>Kaporta</h3>', '<h3>Kaporta & Ön Cephe</h3>')
    hz = hz.replace('<h3>Pasta Cila</h3>', '<h3>Müşteri Danışma (Pasta Cila)</h3>')
    hz = hz.replace('<h3>PDR – Boyasız Göçük Düzeltme</h3>', '<h3>Aktif Atölye (PDR)</h3>')
    hz = hz.replace('<h3>Elektrik</h3>', '<h3>Elektrik Ekipmanları</h3>')
    hz = hz.replace('<h3>Ekspertiz</h3>', '<h3>Ekspertiz Takımları</h3>')

    with open(hizmetler_path, "w", encoding="utf-8") as f:
        f.write(hz)

# Specific fixes for Index
index_path = os.path.join(base_dir, "index.html")
if os.path.exists(index_path):
    with open(index_path, "r", encoding="utf-8") as f:
        idx = f.read()
        
    idx = idx.replace('style="margin-bottom:12px; color:#1a1a2e; font-size:1.25rem;">Kaporta</h3>', 'style="margin-bottom:12px; color:#1a1a2e; font-size:1.25rem;">Kaporta & Ön Cephe</h3>')
    idx = idx.replace('style="margin-bottom:12px; color:#1a1a2e; font-size:1.25rem;">Pasta Cila</h3>', 'style="margin-bottom:12px; color:#1a1a2e; font-size:1.25rem;">Müşteri Danışma (Pasta Cila)</h3>')
    idx = idx.replace('style="margin-bottom:12px; color:#1a1a2e; font-size:1.25rem;">PDR - Boyasız Göçük</h3>', 'style="margin-bottom:12px; color:#1a1a2e; font-size:1.25rem;">Aktif Atölye (PDR)</h3>')
    idx = idx.replace('style="margin-bottom:12px; color:#1a1a2e; font-size:1.25rem;">Elektrik</h3>', 'style="margin-bottom:12px; color:#1a1a2e; font-size:1.25rem;">Elektrik Ekipmanları</h3>')
    idx = idx.replace('style="margin-bottom:12px; color:#1a1a2e; font-size:1.25rem;">Ekspertiz</h3>', 'style="margin-bottom:12px; color:#1a1a2e; font-size:1.25rem;">Ekspertiz Takımları</h3>')

    with open(index_path, "w", encoding="utf-8") as f:
        f.write(idx)

print("Second batch changes completed.")
