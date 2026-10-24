<footer>
  <div class="wrap footer-row">
    <a class="footer-brand" href="?page=home">ASATA MİZAN</a>
    <span class="footer-copy">© <?= date('Y') ?> ASATA MİZAN · Ön fizibilite bilgilendirme amaçlıdır; resmî değerleme veya hukuki görüş değildir.</span>
    <div class="footer-links"><a href="?page=ilanlar">İlanlar</a><a href="?page=proje-ekle">Proje ekle</a><a href="?page=giris">Yönetici</a></div>
  </div>
</footer>
<script>
  const quickArea = document.getElementById('area');
  const quickFloor = document.getElementById('floor');
  const quickResult = document.getElementById('resultArea');
  if (quickArea && quickFloor && quickResult) {
    const formatter = new Intl.NumberFormat('tr-TR', { maximumFractionDigits: 2 });
    const update = () => {
      const area = Math.max(0, Number(quickArea.value) || 0);
      const floor = Math.max(0, Number(quickFloor.value) || 0);
      quickResult.textContent = formatter.format(area * floor);
    };
    quickArea.addEventListener('input', update);
    quickFloor.addEventListener('input', update);
    update();
  }
</script>
</body>
</html>
