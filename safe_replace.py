import os
import glob

base_dir = r"c:\Users\NAZLICAN\Desktop\drn-website"

global_replacements = {
    "drnekinoto.com": "drnekinotoizmit.com",
    "Kaporta & Ön Cephe": "Ön Cephe",
    "Kaporta &amp; Ön Cephe": "Ön Cephe",
    "Müşteri Danışma (Pasta Cila)": "Ofis",
    "Ofis & Bekleme Alanı": "Ofis",
    "Elektrik Ekipmanları": "PDR (Paintless Dent Repair) Ekipmanları"
}

# 1. Global Replaces
for file_path in glob.glob(os.path.join(base_dir, "*.html")) + [os.path.join(base_dir, "sitemap.xml"), os.path.join(base_dir, "robots.txt")]:
    if not os.path.exists(file_path): continue
    with open(file_path, "r", encoding="utf-8") as f:
        content = f.read()
    for old, new in global_replacements.items():
        content = content.replace(old, new)
    with open(file_path, "w", encoding="utf-8") as f:
        f.write(content)

# 2. Exact paragraph replacements in index.html & hizmetler.html
def replace_exact(filename, old_str, new_str):
    path = os.path.join(base_dir, filename)
    if os.path.exists(path):
        with open(path, "r", encoding="utf-8") as f:
            c = f.read()
        c = c.replace(old_str, new_str)
        with open(path, "w", encoding="utf-8") as f:
            f.write(c)

# HIZMETLER.HTML EXACT REPLACEMENTS
# Ön Cephe
hz_cephe_old_p = "<p>D130 Karayolu üzerindeki servisimizde kaza sonrası aracınızı güvenle bırakabilirsiniz. Deneyimli ekibimiz kaportanızı orijinal haline getirinceye kadar yanınızdadır.</p>"
hz_cephe_new_p = "<p>D130 Karayolu üzerindeki modern tesisimizde, araç giriş işlemlerinizin yapıldığı geniş ön cephemiz. Tesisimiz her türlü ihtiyacınıza cevap verebilecek alana sahiptir.</p>"

hz_cephe_old_ul = """<ul class="service-feature-list">
              <li class="service-feature-item"><i class="fa-solid fa-file-contract" style="color:var(--red);"></i> Tüm sigorta şirketleriyle anlaşmamız bulunmaktadır.</li>
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> Kaza hasarı kaporta onarımı</li>
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> Çekme ve şekillendirme</li>
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> Panel değişimi ve kaynak</li>
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> Hasar tespiti ve ekspertiz</li>
            </ul>"""
hz_cephe_new_ul = """<ul class="service-feature-list">
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> Geniş Araç Girişi</li>
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> Misafir Karşılama Alanı</li>
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> Kolay Ulaşım (D130 Üzeri)</li>
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> Açık Otopark</li>
            </ul>"""

replace_exact("hizmetler.html", hz_cephe_old_p, hz_cephe_new_p)
replace_exact("hizmetler.html", hz_cephe_old_ul, hz_cephe_new_ul)

# Ofis
hz_ofis_old_p = "<p>Danışma ofisimizde pasta cila ve seramik kaplama seçeneklerini size özel değerlendiriyoruz. İnce çizikten mat boyaya tüm sorunları gidererek aracınıza showroom parlaklığı kazandırıyoruz.</p>"
hz_ofis_new_p = "<p>Aracınızın işlemleri sürerken veya ekspertiz raporunuzu beklerken misafirimiz olabileceğiniz şık ve rahat bekleme ofisimiz. Çayınızı yudumlarken aracınız emin ellerde.</p>"

hz_ofis_old_ul = """<ul class="service-feature-list">
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> Makine cilası (pasta)</li>
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> El cilası ve wax uygulaması</li>
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> İnce çizik giderme</li>
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> Seramik kaplama</li>
            </ul>"""
hz_ofis_new_ul = """<ul class="service-feature-list">
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> Konforlu Bekleme Alanı</li>
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> İkramlar ve Çay/Kahve</li>
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> Danışma ve Ön Bilgi</li>
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> Sigorta Süreç Yönetimi</li>
            </ul>"""

replace_exact("hizmetler.html", hz_ofis_old_p, hz_ofis_new_p)
replace_exact("hizmetler.html", hz_ofis_old_ul, hz_ofis_new_ul)

# PDR Ekipmanları
hz_pdr_old_p = "<p>Düzenli kablo ve ekipman yönetim sistemimizle arıza tespitini hızlı ve doğru yapıyoruz. Akü kontrolünden ECU programlamaya kadar tüm elektrik işlerinizi çözüyoruz.</p>"
hz_pdr_new_p = "<p>Profesyonel boyasız göçük düzeltme (PDR) işlemlerini kusursuz ve en hızlı şekilde gerçekleştirmek için kullandığımız özel takım ve aydınlatma ekipmanlarımız.</p>"

hz_pdr_old_ul = """<ul class="service-feature-list">
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> Akü test, şarj ve değişim</li>
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> Alternatör ve marş motoru</li>
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> Far ve sinyal sistemi onarımı</li>
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> ECU / beyin programlama</li>
            </ul>"""
hz_pdr_new_ul = """<ul class="service-feature-list">
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> Hassas PDR Aydınlatmaları</li>
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> Profesyonel Çubuk Setleri</li>
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> Boya Zararı Vermeyen Uçlar</li>
              <li class="service-feature-item"><i class="fa-solid fa-check"></i> Vakumlu Çektirme Sistemleri</li>
            </ul>"""

replace_exact("hizmetler.html", hz_pdr_old_p, hz_pdr_new_p)
replace_exact("hizmetler.html", hz_pdr_old_ul, hz_pdr_new_ul)


# INDEX.HTML EXACT REPLACEMENTS
# Ön Cephe
idx_cephe_old = '<p style="font-size:0.95rem; color:#555; margin-bottom:20px;">D130 Karayolu üzerindeki servisimize kolayca ulaşın; kaporta onarımınızı güvenle teslim edin.</p>'
idx_cephe_new = '<p style="font-size:0.95rem; color:#555; margin-bottom:20px;">D130 Karayolu üzerindeki modern tesisimizde, araç kabul ve misafir karşılama alanımız.</p>'
replace_exact("index.html", idx_cephe_old, idx_cephe_new)

# Ofis
idx_ofis_old = '<p style="font-size:0.95rem; color:#555; margin-bottom:20px;">Müşteri danışma ofisimizde pasta cila ve seramik kaplama seçeneklerini size özel değerlendiriyor, en uygun çözümü sunuyoruz.</p>'
idx_ofis_new = '<p style="font-size:0.95rem; color:#555; margin-bottom:20px;">Aracınızın işlemleri sürerken misafirimiz olabileceğiniz şık ve rahat bekleme alanımız.</p>'
replace_exact("index.html", idx_ofis_old, idx_ofis_new)

# PDR Ekipmanları
idx_pdr_old = '<p style="font-size:0.95rem; color:#555; margin-bottom:20px;">Düzenli kablo envanter sistemimizle elektrik arızalarınızı hızlı ve doğru biçimde teşhis ediyoruz.</p>'
idx_pdr_new = '<p style="font-size:0.95rem; color:#555; margin-bottom:20px;">Boyasız göçük düzeltme işlemlerini kusursuz gerçekleştirmek için kullandığımız profesyonel takım ünitelerimiz.</p>'
replace_exact("index.html", idx_pdr_old, idx_pdr_new)

print("Safe replace done.")
