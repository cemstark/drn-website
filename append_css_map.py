import os

base_dir = r"c:\Users\NAZLICAN\Desktop\drn-website"

css_to_append = """
/* Reviews Carousel Missing CSS */
.reviews-carousel-wrap { position: relative; max-width: 1000px; margin: 0 auto; padding: 0 40px; }
.reviews-carousel { overflow: hidden; position: relative; }
.reviews-track { display: flex; gap: 24px; transition: transform 0.4s ease; width: 100%; }
.reviews-track .testimonial-card { flex: 0 0 calc(33.333% - 16px); }
@media (max-width: 1100px) {
  .reviews-track .testimonial-card { flex: 0 0 calc(50% - 12px); }
  .reviews-carousel-wrap { max-width: 700px; }
}
@media (max-width: 640px) {
  .reviews-track .testimonial-card { flex: 0 0 100%; }
  .reviews-carousel-wrap { max-width: 400px; padding: 0 30px; }
}
.carousel-btn {
  position: absolute; top: 50%; transform: translateY(-50%);
  width: 40px; height: 40px;
  background: var(--red); color: #fff;
  border: none; border-radius: 50%;
  cursor: pointer; z-index: 10;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: all 0.3s ease;
}
.carousel-btn:hover { background: var(--red-dark); }
.carousel-prev { left: -10px; }
.carousel-next { right: -10px; }
.carousel-dots { display: flex; justify-content: center; gap: 8px; margin-top: 24px; }
.carousel-dot { width: 10px; height: 10px; border-radius: 50%; background: rgba(30,58,138,0.2); border: none; cursor: pointer; padding: 0; transition: background 0.3s ease; }
.carousel-dot.active { background: var(--red); transform: scale(1.2); }
.tc-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
"""

# Append CSS
with open(os.path.join(base_dir, "css", "style.css"), "a", encoding="utf-8") as f:
    f.write(css_to_append)

# Map HTML
map_iframe = """<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d11746.425175850905!2d29.946369068019385!3d40.74900742562417!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x14cb4f001c90dc09%3A0xc3cf4a40f8ed8f57!2sSanayi%2C%20D-130%20Karayolu%20Cd.%20No%3A123%2C%2041040%20%C4%B0zmit%2FKocaeli%2C%20T%C3%BCrkiye!5e0!3m2!1str!2str!4v1700000000000!5m2!1str!2str" width="100%" height="450" style="border:0; border-radius:12px; margin-top:30px; box-shadow:0 8px 30px rgba(0,0,0,0.1);" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>"""

iletisim_path = os.path.join(base_dir, "iletisim.html")
with open(iletisim_path, "r", encoding="utf-8") as f:
    iletisim = f.read()

iletisim = iletisim.replace('</section>\n\n  <!-- FOOTER -->', '</section>\n  <div class="container" style="margin-bottom: 60px;">' + map_iframe + '</div>\n\n  <!-- FOOTER -->')

with open(iletisim_path, "w", encoding="utf-8") as f:
    f.write(iletisim)

print("Appended CSS and edited iletisim.html Map.")
