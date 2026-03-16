<script>
document.addEventListener("DOMContentLoaded", function() {
    const loader = document.getElementById("page-loader");
    loader.classList.add("hidden"); 

    function isExternalNavigation(link) {
        if (!link.href) return false;
        if(link.getAttribute('href').startsWith('#') || link.getAttribute('href').startsWith('javascript:')) return false;
        if(link.hasAttribute('data-modal')) return false; 
        return link.href.startsWith(window.location.origin);
    }

    document.querySelectorAll('a[href]').forEach(link => {
        if(isExternalNavigation(link)) {
            link.addEventListener('click', function(e){
                e.preventDefault();
                loader.classList.remove("hidden"); 
                const url = this.href;
                setTimeout(() => { window.location.href = url; }, 1000);
            });
        }
    });

    document.querySelectorAll('form').forEach(form => {
        if(form.closest('.custom-modal') || form.closest('.modal') || form.id === 'uploadForm') return; 

        form.addEventListener('submit', function(){
            loader.classList.remove("hidden");
        });
    });
});
</script>
</body>
</html>