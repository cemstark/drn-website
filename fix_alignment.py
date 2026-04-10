import os
import re

base_dir = r"c:\Users\NAZLICAN\Desktop\drn-website"

def inject_icon_placeholder(filename):
    path = os.path.join(base_dir, filename)
    if not os.path.exists(path):
        return
    with open(path, "r", encoding="utf-8") as f:
        content = f.read()

    # Define the cards we need to fix
    # We look for the h3 text to identify the card, and then find the start of its inner content
    
    # 1. Rot - Balance
    # index.html uses "Rot - Balance", hizmetler.html uses "Rot – Balance" (en dash) or similar.
    # Actually we can just find the comment markers that exist in index.html and hizmetler.html
    # "<!-- 6. ROT BALANCE -->" or "<!-- 6. ROT-BALANCE -->"
    
    # Let's write a generic injector based on h3 text
    # The structure without image is:
    # <div class="service-detail-card ...">
    #   <div class="(service-detail-body|service-detail-header)">
    
    updates = [
        {"regex": r'(<div class="service-detail-card[^>]*>)\s*(<div class="service-detail-body">[^<]*<div class="card-brand"[^>]*>.*?<h3[^>]*>Rot - Balance</h3>)', 
         "icon": "fa-rotate", "match": "Rot - Balance (index)"},
        {"regex": r'(<div class="service-detail-card[^>]*>)\s*(<div class="service-detail-header">\s*<div class="service-detail-icon"><i class="fa-solid fa-rotate"></i></div>\s*<h3[^>]*>Rot – Balance</h3>)',
         "icon": "fa-rotate", "match": "Rot – Balance (hizmetler)"},
         
        {"regex": r'(<div class="service-detail-card[^>]*>)\s*(<div class="service-detail-body">[^<]*<div class="card-brand"[^>]*>.*?<h3[^>]*>Ekspertiz Takımları</h3>)',
         "icon": "fa-magnifying-glass-chart", "match": "Ekspertiz (index)"},
        {"regex": r'(<div class="service-detail-card[^>]*>)\s*(<div class="service-detail-header">\s*<div class="service-detail-icon"><i class="fa-solid fa-magnifying-glass-chart"></i></div>\s*<h3[^>]*>Ekspertiz Takımları</h3>)',
         "icon": "fa-magnifying-glass-chart", "match": "Ekspertiz (hizmetler)"},
         
        {"regex": r'(<div class="service-detail-card[^>]*>)\s*(<div class="service-detail-body">[^<]*<div class="card-brand"[^>]*>.*?<h3[^>]*>Mekanik</h3>)',
         "icon": "fa-gears", "match": "Mekanik (index)"},
        {"regex": r'(<div class="service-detail-card[^>]*>)\s*(<div class="service-detail-header">\s*<div class="service-detail-icon"><i class="fa-solid fa-gears"></i></div>\s*<h3[^>]*>Mekanik</h3>)',
         "icon": "fa-gears", "match": "Mekanik (hizmetler)"}
    ]
    
    for u in updates:
        def replacer(m):
            card_start = m.group(1)
            inner_content = m.group(2)
            placeholder = f'\n          <div class="service-detail-img-wrap" style="background:#f1f5f9; display:flex; align-items:center; justify-content:center;">\n            <i class="fa-solid {u["icon"]}" style="font-size:4.5rem; color:var(--red, #e63946); opacity:0.85;"></i>\n          </div>\n          '
            return card_start + placeholder + inner_content
            
        content = re.sub(u["regex"], replacer, content, flags=re.DOTALL)
        
    with open(path, "w", encoding="utf-8") as f:
        f.write(content)

inject_icon_placeholder("index.html")
inject_icon_placeholder("hizmetler.html")
print("Alignments fixed.")
