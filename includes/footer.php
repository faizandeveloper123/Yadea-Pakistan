<?php if (!defined('HIIFI')) exit('Direct access not allowed.'); ?>
        </div>
<script src="<?php echo BASE_URL; ?>assets/js/jqueryfile.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/bootstrap.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/custom.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/jquery.sparkline.min.js"></script>
<script>
(function(){
    // The shell now uses the expanded Gentelella-blue sidebar by default.
    // Always show expanded on load so any previously stored collapsed
    // preference cannot override the new theme.
    document.body.className = 'sidebar-expanded';
})();
document.addEventListener('dblclick', function(e){
    if (e.target.closest('#sidebar-menu, .sidebar-logo, .left_col')) {
        var exp = document.body.classList.contains('sidebar-expanded');
        document.body.classList.toggle('sidebar-collapsed', exp);
        document.body.classList.toggle('sidebar-expanded', !exp);
        localStorage.setItem('sb_collapsed', exp ? '1' : '0');
    }
});
document.addEventListener('click', function(e){
    var item = e.target.closest('.side-menu>li>a, .side-menu>li.has-children>a');
    if (item) {
        var li = item.parentElement;
        if (li.classList.contains('has-children')) {
            var menu = li.querySelector(':scope > .child_menu');
            if (menu) {
                e.preventDefault();
                document.querySelectorAll('.side-menu>li>.child_menu').forEach(function(m){ if (m !== menu) m.style.display='none'; });
                var isOpen = menu.style.display === 'block';
                document.querySelectorAll('.side-menu>li').forEach(function(l){ l.classList.remove('active'); });
                li.classList.add('active');
                menu.style.display = isOpen ? 'none' : 'block';
            }
        }
    }
});
function slideout(){ setTimeout(function(){
    $(".alert-success").fadeOut("slow", function () { });
    $(".alert-danger").fadeOut("slow", function () { });
}, 4000);}
</script>
</body></html>