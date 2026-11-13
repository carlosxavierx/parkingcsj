#!/usr/bin/env python3
"""Genera 06-diagramas.html a partir dels blocs ```mermaid dels documents .md.

Ús:  python3 generar-diagramas.py
Així els diagrames de la presentació sempre coincideixen amb la documentació.
"""
import html
import re
from pathlib import Path

DIR = Path(__file__).parent
DOCS = ["01-esquema-funcional.md", "02-modelo-er.md", "04-planificacion.md"]


def extreu(md_path):
    """Retorna [(titol, codi_mermaid)] fent servir l'últim encapçalament abans de cada bloc."""
    titol, resultats, dins, codi = "", [], False, []
    for linia in md_path.read_text(encoding="utf-8").splitlines():
        if not dins and re.match(r"^#{2,4} ", linia):
            titol = re.sub(r"^#+\s*", "", linia)
        elif linia.strip() == "```mermaid":
            dins, codi = True, []
        elif dins and linia.strip() == "```":
            dins = False
            resultats.append((titol, "\n".join(codi)))
        elif dins:
            codi.append(linia)
    return resultats


seccions = []
n = 0
for doc in DOCS:
    for titol, codi in extreu(DIR / doc):
        n += 1
        seccions.append(
            f'<section>\n  <h2>{n} · {html.escape(titol)}</h2>\n'
            f'  <pre class="mermaid">\n{html.escape(codi)}\n  </pre>\n'
            f'  <p class="note">Font: {doc}</p>\n</section>'
        )

pagina = f"""<!DOCTYPE html>
<html lang="ca">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Diagrames Tarraco Parkings</title>
<style>
  :root {{ --bg:#0f172a; --card:#1e293b; --text:#e2e8f0; --accent:#f59e0b; --muted:#94a3b8; }}
  * {{ box-sizing:border-box; margin:0; padding:0; }}
  body {{ background:var(--bg); color:var(--text); font-family:"Segoe UI",system-ui,sans-serif; padding:2rem 1rem; }}
  header {{ text-align:center; margin-bottom:2rem; }}
  header h1 {{ color:var(--accent); font-size:1.8rem; }}
  header p {{ color:var(--muted); margin-top:.4rem; }}
  section {{ background:var(--card); border-radius:12px; padding:1.5rem; margin:0 auto 2rem;
            max-width:1200px; overflow-x:auto; }}
  section h2 {{ font-size:1.2rem; margin-bottom:1rem; padding-bottom:.5rem; border-bottom:2px solid var(--accent); }}
  .mermaid {{ text-align:center; background:transparent; }}
  .note {{ font-size:.8rem; color:var(--muted); margin-top:1rem; text-align:right; }}
  footer {{ text-align:center; color:var(--muted); font-size:.85rem; padding:1rem; }}
  @media print {{ body {{ background:#fff; color:#000; }} section {{ break-inside:avoid; background:#fff; }} }}
</style>
<script src="https://cdn.jsdelivr.net/npm/mermaid@10.9.1/dist/mermaid.min.js"></script>
<script>mermaid.initialize({{ startOnLoad:true, theme:"dark", securityLevel:"loose" }});</script>
</head>
<body>
<header>
  <h1>Tarraco Parkings · Diagrames del Sprint 1 (v2)</h1>
  <p>Carlos · Sara · Joel — generat automàticament amb generar-diagramas.py</p>
</header>
{chr(10).join(seccions)}
<footer>Sistema de Reserves de Places de Pàrquing · Tarragona · 2026</footer>
</body>
</html>
"""
(DIR / "06-diagramas.html").write_text(pagina, encoding="utf-8")
print(f"06-diagramas.html generat amb {n} diagrames")
