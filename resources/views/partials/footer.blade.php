<footer class="site-footer">
    <div class="container footer-shell">
        <div class="footer-brand-block">
            <a class="footer-brand" href="/">
                <img class="brand-logo" src="{{ asset('img/fluxconvert-logo.svg') }}" alt="FluxConvert logo">
                <span>FluxConvert</span>
            </a>
            <p class="mb-0">Modern file conversion for documents, media, images, and archives with a cleaner workflow built for speed and clarity.</p>
        </div>
        <div class="footer-links-grid">
            <div>
                <span class="footer-title">Product</span>
                <div class="footer-links">
                    <a href="/#features">Features</a>
                    <a href="/#solutions">Solutions</a>
                </div>
            </div>
            <div>
                <span class="footer-title">Access</span>
                <div class="footer-links">
                    @if($currentUser ?? false)
                        <a href="/my-files">My Files</a>
                        <a href="/signout">Sign Out</a>
                    @else
                        <a href="/signin">Sign In</a>
                        <a href="/signup">Sign Up</a>
                    @endif
                    <a href="/#converter">Launch Tool</a>
                </div>
            </div>
        </div>
        <div class="footer-socials">
            <a href="#" class="footer-social-link" aria-label="Facebook"><span class="social-tooltip">Facebook</span><i class="bi bi-facebook"></i></a>
            <a href="#" class="footer-social-link" aria-label="Instagram"><span class="social-tooltip">Instagram</span><i class="bi bi-instagram"></i></a>
            <a href="#" class="footer-social-link" aria-label="Twitter"><span class="social-tooltip">Twitter</span><i class="bi bi-twitter-x"></i></a>
            <a href="#" class="footer-social-link" aria-label="LinkedIn"><span class="social-tooltip">LinkedIn</span><i class="bi bi-linkedin"></i></a>
        </div>
    </div>
    <div class="container footer-bottom">
        <span>&copy; 2026 FluxConvert</span>
        <div class="footer-bottom-links">
            <a href="#">Privacy</a>
            <a href="#">Terms</a>
            <a href="#">Support</a>
        </div>
    </div>
</footer>
@include('partials.whatsapp-chat')
