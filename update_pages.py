import os
import re

html_files = [
    "index.html", "hakkimizda.html", "hizmetler.html", "galeri.html", 
    "iletisim.html", "blog.html", "sss.html", "randevu.html"
]

base_dir = r"c:\Users\NAZLICAN\Desktop\drn-website"

tags_head = """
  <link rel="icon" type="image/png" href="images/logo-small.png">
  <!-- Google Analytics -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-1234567890"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-1234567890');
  </script>
  <!-- Schema Markup -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "AutoRepair",
    "name": "EKİN OTO SERVİS",
    "image": "https://drnekinoto.com/images/logo-big.png",
    "@id": "https://drnekinoto.com",
    "url": "https://drnekinoto.com",
    "telephone": "+905448500802",
    "address": {
      "@type": "PostalAddress",
      "streetAddress": "Sanayi Mah. D130 Karayolu Cad. No:123",
      "addressLocality": "İzmit",
      "addressRegion": "Kocaeli",
      "addressCountry": "TR"
    }
  }
  </script>
"""

nav_logo_html = '<img src="images/logo-medium.png" alt="EKİN OTO SERVİS" class="logo-img">'
footer_logo_html = '<img src="images/logo-big.png" alt="EKİN OTO SERVİS" class="footer-logo-img">'

for file in html_files:
    file_path = os.path.join(base_dir, file)
    if not os.path.exists(file_path):
        continue
        
    with open(file_path, "r", encoding="utf-8") as f:
        content = f.read()
    
    # 1. SEO/Favicon/Head tags
    if "G-1234567890" not in content:
        content = content.replace("</head>", tags_head + "</head>")
        
    # 2. Navbar logo replacing text-logo
    text_logo_pattern = r'<div class="text-logo">.*?</div>'
    content = re.sub(text_logo_pattern, nav_logo_html, content, count=1, flags=re.DOTALL)
    
    # Also replace if it was already an img tag pointing to logo.png
    content = re.sub(r'<img src="images/logo\.png"[^>]*class="logo-img">', nav_logo_html, content)

    # 3. Footer text logo replace
    footer_text_logo_pattern = r'<div class="footer-text-logo">.*?</div>'
    content = re.sub(footer_text_logo_pattern, footer_logo_html, content, count=1, flags=re.DOTALL)
    
    # Also replace if already using logo.png in footer
    content = re.sub(r'<img src="images/logo\.png"[^>]*class="footer-logo-img">', footer_logo_html, content)

    # 4. Social links in footer
    content = content.replace('<a href="#" class="social-link"', '<a href="https://instagram.com" target="_blank" rel="noopener" class="social-link"')
    
    # 5. Dynamic year
    content = re.sub(r'© 202[56] DRN', '© <script>document.write(new Date().getFullYear())</script> EKİN', content)
    content = re.sub(r'© 202[56] EKİN', '© <script>document.write(new Date().getFullYear())</script> EKİN', content)
    
    with open(file_path, "w", encoding="utf-8") as f:
        f.write(content)

print("Batch updates applied successfully.")
