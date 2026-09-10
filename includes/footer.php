<?php if ($__logged_in): ?>
    </div><!-- .content -->
  </div><!-- .main -->
</div><!-- .app-shell -->
<script>
  (function () {
    var search = document.getElementById('dash-search');
    var table = document.querySelector('.data-table');
    if (!search || !table) return;
    var rows = table.querySelectorAll('tbody tr');
    search.addEventListener('input', function () {
      var q = search.value.trim().toLowerCase();
      rows.forEach(function (row) {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  })();
</script>
<?php else: ?>
  </div><!-- .auth-page -->
</div><!-- .auth-shell -->
<?php endif; ?>
</body>
</html>
