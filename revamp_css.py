import os

css_path = r"c:\Users\NAZLICAN\Desktop\drn-website\css\style.css"

if os.path.exists(css_path):
    with open(css_path, "r", encoding="utf-8") as f:
        css = f.read()

    css = css.replace("--radius-sm: 4px;", "--radius-sm: 8px;")
    css = css.replace("--radius: 8px;", "--radius: 16px;")
    css = css.replace("--radius-lg: 16px;", "--radius-lg: 24px;")
    css = css.replace("--shadow-sm: 0 2px 10px rgba(15,30,61,0.08);", "--shadow-sm: 0 4px 15px rgba(0,0,0,0.05);")
    css = css.replace("--shadow: 0 4px 20px rgba(15,30,61,0.12);", "--shadow: 0 10px 30px rgba(0,0,0,0.08);")
    css = css.replace("--shadow-lg: 0 8px 40px rgba(15,30,61,0.16);", "--shadow-lg: 0 20px 40px rgba(0,0,0,0.12);")
    css = css.replace("--dark: #0f1e3d;", "--dark: #0f172a;")
    
    old_logo_css = """.logo-img {
  max-height: 48px;
  width: auto;
  object-fit: contain;
  display: block;
  transition: transform 0.3s ease;
}"""
    new_logo_css = """.logo-img {
  max-height: 40px;
  width: auto;
  object-fit: contain;
  display: block;
  transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
  filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2));
}"""
    css = css.replace(old_logo_css, new_logo_css)
    
    old_footer_logo = """.footer-logo-img {
  max-height: 80px;
  width: auto;
  object-fit: contain;
  display: block;
  margin-bottom: 20px;
}"""
    new_footer_logo = """.footer-logo-img {
  max-height: 70px;
  width: auto;
  object-fit: contain;
  display: block;
  margin-bottom: 24px;
  filter: drop-shadow(0 4px 10px rgba(0,0,0,0.3));
}"""
    css = css.replace(old_footer_logo, new_footer_logo)

    with open(css_path, "w", encoding="utf-8") as f:
        f.write(css)
print("CSS modernization applied.")
