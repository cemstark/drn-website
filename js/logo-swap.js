/* Replaces legacy logo image references with the cleaned DRNEKİN OTO SVG logo. */
(function () {
  var logoSrc = 'images/drn-logo.svg?v=20260424';
  var selectors = [
    'img.logo-img',
    'img.hero-logo',
    'img.mobile-nav-logo',
    'img.page-header-logo',
    'img.footer-logo-img',
    'img[src*="logo-wide"]',
    'img[src*="logo-medium"]',
    'img[src*="logo-small"]',
    'img[src*="drn-logo.png"]'
  ].join(',');

  function swapLogos() {
    document.querySelectorAll(selectors).forEach(function (img) {
      img.setAttribute('src', logoSrc);
      img.setAttribute('alt', 'DRNEKİN OTO');
      img.style.background = 'transparent';
      img.style.objectFit = 'contain';
    });

    var favicon = document.querySelector('link[rel="icon"]');
    if (favicon) {
      favicon.setAttribute('type', 'image/svg+xml');
      favicon.setAttribute('href', logoSrc);
    }

    var appleIcon = document.querySelector('link[rel="apple-touch-icon"]');
    if (appleIcon) appleIcon.setAttribute('href', logoSrc);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', swapLogos);
  } else {
    swapLogos();
  }
})();
