#!/usr/bin/env python3
"""ASATA MIZAN — İş sonu numeric + grafik durum raporu üreteci.

Kullanım: python3 tools/durum_raporu.py [etiket]

PROJECT_STATUS.md içindeki faz tamamlanma tablosunu, git istatistiklerini ve
Drive yedek sayısını okuyup raporlar/ altına şu iki çıktıyı üretir:
  - DURUM_RAPORU_<zaman>.md  (numeric rapor)
  - durum_grafik_<zaman>.png (grafik: faz çubukları + genel ilerleme)

Gereksinimler: python3, matplotlib; gws CLI (Drive yedek sayımı için, yoksa 0 yazılır).
"""
import datetime
import json
import re
import subprocess
import sys
from pathlib import Path

KOK = Path(__file__).resolve().parent.parent
RAPOR_DIR = KOK / "raporlar"
ETIKET = sys.argv[1] if len(sys.argv) > 1 else "is-sonu"
STAMP = datetime.datetime.now().strftime("%Y%m%d-%H%M%S")


def git(*args):
    try:
        return subprocess.check_output(
            ["git", "-C", str(KOK), *args], text=True
        ).strip()
    except subprocess.CalledProcessError:
        return ""


def fazlar():
    """PROJECT_STATUS.md'deki '| N — ad | %X | ...' satırlarını ayrıştırır."""
    metin = (KOK / "PROJECT_STATUS.md").read_text(encoding="utf-8")
    satirlar = []
    for satir in metin.splitlines():
        m = re.match(r"\|\s*(\d+(?:–\d+|-\d+)?)\s*[—–-]\s*([^|]+?)\s*\|\s*%?(\d+)", satir)
        if m:
            satirlar.append((m.group(1), m.group(2).strip(), int(m.group(3))))
    return satirlar


def mvp_toplamlari():
    metin = (KOK / "PROJECT_STATUS.md").read_text(encoding="utf-8")
    mvp = re.search(r"Dar MVP[^|]*\*\s*yaklaşık %(\d+)", metin)
    tum = re.search(r"Tüm ürün vizyonu[^|]*\*\s*yaklaşık %(\d+)", metin)
    return (
        int(mvp.group(1)) if mvp else None,
        int(tum.group(1)) if tum else None,
    )


def gorev_sayilari():
    metin = (KOK / "PROJECT_STATUS.md").read_text(encoding="utf-8")
    bolum = metin.split("## Anlık görev panosu", 1)[-1].split("##", 1)[0]
    sayilar = {"✅": 0, "🟡": 0, "⏳": 0, "⏸️": 0}
    for satir in bolum.splitlines():
        for k in sayilar:
            if satir.strip().startswith("| " + k):
                sayilar[k] += 1
    return sayilar


def drive_yedek_sayisi():
    klasor = (KOK / "tools" / "drive_klasor_id.txt").read_text().strip()
    try:
        cikti = subprocess.check_output(
            ["gws", "drive", "files", "list",
             "--params", json.dumps({
                 "q": f"'{klasor}' in parents and trashed=false",
                 "fields": "files(id,name)",
                 "pageSize": 200})],
            text=True,
        )
        return len(json.loads(cikti).get("files", []))
    except Exception:
        return None


def main():
    RAPOR_DIR.mkdir(exist_ok=True)
    fz = fazlar()
    mvp, tum = mvp_toplamlari()
    gorevler = gorev_sayilari()
    yedek = drive_yedek_sayisi()

    commit_say = git("rev-list", "--count", "HEAD") or "?"
    son_commit = git("log", "-1", "--format=%h %s (%ci)")
    dosya_say = git("ls-files") or ""
    dosya_say = len(dosya_say.splitlines()) if dosya_say else 0
    degisiklik = git("status", "--porcelain")

    rapor = RAPOR_DIR / f"DURUM_RAPORU_{STAMP}.md"
    satirlar = [
        f"# ASATA MIZAN — İş Sonu Durum Raporu",
        "",
        f"- **Zaman:** {datetime.datetime.now().isoformat(timespec='seconds')}",
        f"- **Etiket:** {ETIKET}",
        f"- **Son commit:** {son_commit or 'yok'}",
        f"- **Toplam commit:** {commit_say} · **İzlenen dosya:** {dosya_say}",
        f"- **İşlenmemiş değişiklik:** {len(degisiklik.splitlines()) if degisiklik else 0} dosya",
        f"- **Drive yedek sayısı (ASATA_MIZAN):** {yedek if yedek is not None else 'sayılamadı'}",
        "",
        "## Numeric göstergeler",
        "",
        "| Gösterge | Değer |",
        "|---|---:|",
        f"| Dar MVP (Faz 0–5) tamamlanma | %{mvp if mvp is not None else '?'} |",
        f"| Tüm ürün vizyonu (Faz 0–10) | %{tum if tum is not None else '?'} |",
        f"| Görev panosu — tamamlandı | {gorevler['✅']} |",
        f"| Görev panosu — kısmi | {gorevler['🟡']} |",
        f"| Görev panosu — yapılacak | {gorevler['⏳']} |",
        f"| Görev panosu — beklemede/faz dışı | {gorevler['⏸️']} |",
        "",
        "## Faz bazında tamamlanma",
        "",
        "| Faz | Kapsam | Tamamlanma |",
        "|---|---|---:|",
    ]
    for no, ad, yuzde in fz:
        satirlar.append(f"| {no} | {ad} | %{yuzde} |")
    satirlar += ["", f"Grafik: `raporlar/durum_grafik_{STAMP}.png`", ""]
    rapor.write_text("\n".join(satirlar), encoding="utf-8")

    import matplotlib
    matplotlib.use("Agg")
    import matplotlib.pyplot as plt

    fig, (ax1, ax2) = plt.subplots(
        1, 2, figsize=(12, 0.9 * max(len(fz), 1) + 2),
        gridspec_kw={"width_ratios": [3, 1]}
    )
    etiketler = [f"Faz {no} — {ad[:34]}" for no, ad, _ in fz]
    degerler = [y for _, _, y in fz]
    renkler = ["#2e7d32" if y >= 80 else "#f9a825" if y >= 30 else "#c62828" for y in degerler]
    ax1.barh(etiketler[::-1], degerler[::-1], color=renkler[::-1])
    ax1.set_xlim(0, 100)
    for i, y in enumerate(degerler[::-1]):
        ax1.text(min(y + 2, 97), i, f"%{y}", va="center", fontsize=9)
    ax1.set_xlabel("Tamamlanma (%)")
    ax1.set_title("ASATA MIZAN — Faz bazında ilerleme")

    genel = [("Dar MVP (0–5)", mvp), ("Tüm vizyon (0–10)", tum)]
    ax2.barh([a for a, _ in genel][::-1], [v or 0 for _, v in genel][::-1],
             color=["#1565c0", "#5e35b1"][::-1])
    ax2.set_xlim(0, 100)
    for i, (_, v) in enumerate(genel[::-1]):
        ax2.text((v or 0) + 2, i, f"%{v if v is not None else '?'}", va="center", fontsize=9)
    ax2.set_title("Genel ilerleme")

    fig.tight_layout()
    png = RAPOR_DIR / f"durum_grafik_{STAMP}.png"
    fig.savefig(png, dpi=140)
    print(rapor)
    print(png)


if __name__ == "__main__":
    main()
