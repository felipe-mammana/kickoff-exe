from __future__ import annotations

import re
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "docs" / "documentacao-gestor.md"
OUTPUT = ROOT / "output" / "pdf" / "documentacao-projeto-exe-inventario-ti.pdf"

PAGE_W = 595.28
PAGE_H = 841.89
MARGIN_L = 54
MARGIN_R = 54
MARGIN_T = 58
MARGIN_B = 58
BLUE = (0.0, 0.36, 0.72)
DARK = (0.09, 0.12, 0.16)
MUTED = (0.33, 0.38, 0.45)
LIGHT = (0.93, 0.96, 0.99)


def esc(text: str) -> str:
    return text.replace("\\", "\\\\").replace("(", "\\(").replace(")", "\\)")


def clean(text: str) -> str:
    replacements = {
        "–": "-",
        "—": "-",
        "“": '"',
        "”": '"',
        "’": "'",
        "á": "a",
        "à": "a",
        "ã": "a",
        "â": "a",
        "é": "e",
        "ê": "e",
        "í": "i",
        "ó": "o",
        "ô": "o",
        "õ": "o",
        "ú": "u",
        "ç": "c",
        "Á": "A",
        "À": "A",
        "Ã": "A",
        "Â": "A",
        "É": "E",
        "Ê": "E",
        "Í": "I",
        "Ó": "O",
        "Ô": "O",
        "Õ": "O",
        "Ú": "U",
        "Ç": "C",
    }
    for old, new in replacements.items():
        text = text.replace(old, new)
    return text


def width_estimate(text: str, size: float, mono: bool = False) -> float:
    factor = 0.60 if mono else 0.52
    wide = sum(1 for ch in text if ch.isupper() or ch in "MW@#%")
    return (len(text) * factor + wide * 0.08) * size


def wrap(text: str, max_width: float, size: float, mono: bool = False) -> list[str]:
    text = clean(text.strip())
    if not text:
        return [""]
    words = text.split()
    lines: list[str] = []
    current = ""
    for word in words:
        candidate = word if not current else current + " " + word
        if width_estimate(candidate, size, mono) <= max_width:
            current = candidate
        else:
            if current:
                lines.append(current)
            while width_estimate(word, size, mono) > max_width and len(word) > 8:
                cut = max(8, int(max_width / (size * (0.60 if mono else 0.52))))
                lines.append(word[:cut])
                word = word[cut:]
            current = word
    if current:
        lines.append(current)
    return lines


class PdfDoc:
    def __init__(self) -> None:
        self.pages: list[list[str]] = []
        self.current: list[str] = []
        self.y = PAGE_H - MARGIN_T
        self.page_no = 0

    def rgb(self, color: tuple[float, float, float]) -> str:
        return f"{color[0]:.3f} {color[1]:.3f} {color[2]:.3f}"

    def add_page(self) -> None:
        if self.current:
            self.footer()
            self.pages.append(self.current)
        self.page_no += 1
        self.current = []
        self.y = PAGE_H - MARGIN_T
        self.header()

    def header(self) -> None:
        if self.page_no == 1:
            return
        self.rect(MARGIN_L, PAGE_H - 42, PAGE_W - MARGIN_L - MARGIN_R, 2, BLUE)
        self.text("EXE Inventario TI", MARGIN_L, PAGE_H - 32, 8.5, "F2", MUTED)
        self.text("Documentacao do projeto", PAGE_W - MARGIN_R - 118, PAGE_H - 32, 8.5, "F1", MUTED)

    def footer(self) -> None:
        self.text(f"Pagina {self.page_no}", PAGE_W - MARGIN_R - 42, 31, 8, "F1", MUTED)

    def ensure(self, height: float) -> None:
        if self.y - height < MARGIN_B:
            self.add_page()

    def text(self, text: str, x: float, y: float, size: float, font: str = "F1", color: tuple[float, float, float] = DARK) -> None:
        self.current.append(f"BT /{font} {size:.2f} Tf {self.rgb(color)} rg 1 0 0 1 {x:.2f} {y:.2f} Tm ({esc(clean(text))}) Tj ET")

    def rect(self, x: float, y: float, w: float, h: float, color: tuple[float, float, float]) -> None:
        self.current.append(f"q {self.rgb(color)} rg {x:.2f} {y:.2f} {w:.2f} {h:.2f} re f Q")

    def line(self, x1: float, y1: float, x2: float, y2: float, color: tuple[float, float, float] = MUTED, width: float = 0.6) -> None:
        self.current.append(f"q {self.rgb(color)} RG {width:.2f} w {x1:.2f} {y1:.2f} m {x2:.2f} {y2:.2f} l S Q")

    def paragraph(self, text: str, size: float = 10.4, indent: float = 0, bullet: bool = False, font: str = "F1", color: tuple[float, float, float] = DARK) -> None:
        max_width = PAGE_W - MARGIN_L - MARGIN_R - indent
        lines = wrap(text, max_width - (12 if bullet else 0), size)
        self.ensure(len(lines) * (size + 4) + 5)
        first_x = MARGIN_L + indent
        if bullet:
            self.text("-", first_x, self.y, size, "F2", BLUE)
            first_x += 12
        for i, line in enumerate(lines):
            x = first_x if i == 0 else MARGIN_L + indent + (12 if bullet else 0)
            self.text(line, x, self.y, size, font, color)
            self.y -= size + 4
        self.y -= 3

    def heading(self, text: str, level: int) -> None:
        if level == 2:
            self.ensure(40)
            self.y -= 9
            self.text(clean(text), MARGIN_L, self.y, 16.2, "F2", BLUE)
            self.y -= 10
            self.line(MARGIN_L, self.y, PAGE_W - MARGIN_R, self.y, (0.78, 0.84, 0.9), 0.7)
            self.y -= 18
        else:
            self.ensure(28)
            self.y -= 5
            self.text(clean(text), MARGIN_L, self.y, 12.2, "F2", DARK)
            self.y -= 18

    def code_block(self, lines: list[str]) -> None:
        size = 8.8
        line_h = 12
        height = len(lines) * line_h + 18
        self.ensure(height)
        self.rect(MARGIN_L, self.y - height + 8, PAGE_W - MARGIN_L - MARGIN_R, height, LIGHT)
        self.line(MARGIN_L, self.y - height + 8, PAGE_W - MARGIN_R, self.y - height + 8, (0.75, 0.82, 0.9), 0.5)
        y = self.y - 10
        for raw in lines:
            for part in wrap(raw, PAGE_W - MARGIN_L - MARGIN_R - 22, size, mono=True):
                self.text(part, MARGIN_L + 11, y, size, "F3", DARK)
                y -= line_h
        self.y = y - 6

    def table(self, rows: list[list[str]]) -> None:
        if not rows:
            return
        widths = [58, 172, 45, 45, 160]
        row_h = 24
        x0 = MARGIN_L
        for idx, row in enumerate(rows):
            needed = row_h
            for col, cell in enumerate(row):
                needed = max(needed, len(wrap(cell, widths[col] - 8, 7.4)) * 9 + 9)
            self.ensure(needed + 5)
            bg = (0.89, 0.94, 0.99) if idx == 0 else ((0.98, 0.99, 1.0) if idx % 2 == 0 else (1, 1, 1))
            self.rect(x0, self.y - needed + 6, sum(widths), needed, bg)
            x = x0 + 4
            for col, cell in enumerate(row):
                font = "F2" if idx == 0 else "F1"
                color = BLUE if idx == 0 else DARK
                y = self.y - 9
                for line in wrap(cell.replace("`", ""), widths[col] - 8, 7.4):
                    self.text(line, x, y, 7.4, font, color)
                    y -= 9
                x += widths[col]
            self.y -= needed
        self.y -= 8

    def cover(self) -> None:
        self.add_page()
        self.rect(0, PAGE_H - 210, PAGE_W, 210, (0.03, 0.12, 0.22))
        self.rect(0, PAGE_H - 214, PAGE_W, 5, BLUE)
        self.text("EXE", MARGIN_L, PAGE_H - 92, 28, "F2", (1, 1, 1))
        self.text("Inventario TI", MARGIN_L, PAGE_H - 128, 23, "F2", (1, 1, 1))
        self.text("Documentacao do Projeto", MARGIN_L, PAGE_H - 164, 14, "F1", (0.82, 0.9, 1))
        self.y = PAGE_H - 270
        self.paragraph("Sistema web interno para cadastro, organizacao, auditoria e integracao de ativos de tecnologia.", 13, font="F2")
        self.paragraph("Documento preparado para apresentacao gerencial, cobrindo objetivos, funcionalidades, seguranca, arquitetura, API e proximos passos.", 10.6)
        self.y -= 12
        for item in [
            "Inventario centralizado por empresa",
            "Auditoria operacional e rastreabilidade",
            "API REST versionada para integracoes",
            "Criptografia de credenciais tecnicas",
            "Uploads validados e controle de sessao",
        ]:
            self.paragraph(item, 10.5, bullet=True)
        self.text("Gerado a partir da documentacao do repositorio", MARGIN_L, 70, 9, "F1", MUTED)

    def finish(self) -> bytes:
        if self.current:
            self.footer()
            self.pages.append(self.current)
            self.current = []

        objects: list[bytes] = []
        objects.append(b"<< /Type /Catalog /Pages 2 0 R >>")
        kids = " ".join(f"{3 + i * 2} 0 R" for i in range(len(self.pages)))
        objects.append(f"<< /Type /Pages /Kids [{kids}] /Count {len(self.pages)} >>".encode())

        font_dict = "<< /F1 1 0 R /F2 2 0 R /F3 3 0 R >>"
        # Actual font objects are appended at the end; page resource ids are shifted during writing.
        page_objs: list[bytes] = []
        content_objs: list[bytes] = []
        font_start = 3 + len(self.pages) * 2
        font_resources = f"<< /F1 {font_start} 0 R /F2 {font_start + 1} 0 R /F3 {font_start + 2} 0 R >>"
        for i, commands in enumerate(self.pages):
            page_id = 3 + i * 2
            content_id = page_id + 1
            page_objs.append(
                f"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {PAGE_W:.2f} {PAGE_H:.2f}] /Resources << /Font {font_resources} >> /Contents {content_id} 0 R >>".encode()
            )
            stream = "\n".join(commands).encode("latin-1", errors="replace")
            content_objs.append(b"<< /Length " + str(len(stream)).encode() + b" >>\nstream\n" + stream + b"\nendstream")

        final_objects = [objects[0], objects[1]]
        for page, content in zip(page_objs, content_objs):
            final_objects.append(page)
            final_objects.append(content)
        final_objects.extend([
            b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
            b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>",
            b"<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>",
        ])

        out = bytearray(b"%PDF-1.4\n%\xe2\xe3\xcf\xd3\n")
        offsets = [0]
        for i, obj in enumerate(final_objects, start=1):
            offsets.append(len(out))
            out.extend(f"{i} 0 obj\n".encode())
            out.extend(obj)
            out.extend(b"\nendobj\n")
        xref = len(out)
        out.extend(f"xref\n0 {len(final_objects) + 1}\n".encode())
        out.extend(b"0000000000 65535 f \n")
        for offset in offsets[1:]:
            out.extend(f"{offset:010d} 00000 n \n".encode())
        out.extend(f"trailer\n<< /Size {len(final_objects) + 1} /Root 1 0 R >>\nstartxref\n{xref}\n%%EOF\n".encode())
        return bytes(out)


def markdown_to_pdf() -> None:
    text = SOURCE.read_text(encoding="utf-8")
    lines = text.splitlines()
    doc = PdfDoc()
    doc.cover()
    doc.add_page()

    in_code = False
    code_lines: list[str] = []
    table_rows: list[list[str]] = []

    def flush_table() -> None:
        nonlocal table_rows
        if table_rows:
            rows = [row for row in table_rows if not all(set(cell.strip()) <= {"-", " "} for cell in row)]
            doc.table(rows)
            table_rows = []

    for line in lines:
        raw = line.rstrip()
        if raw.startswith("```"):
            if in_code:
                doc.code_block(code_lines)
                code_lines = []
                in_code = False
            else:
                flush_table()
                in_code = True
            continue
        if in_code:
            code_lines.append(raw)
            continue
        if raw.startswith("|") and raw.endswith("|"):
            cells = [cell.strip() for cell in raw.strip("|").split("|")]
            if len(cells) >= 5:
                table_rows.append(cells[:5])
            continue
        flush_table()
        if not raw.strip():
            doc.y -= 5
            continue
        if raw.startswith("# "):
            continue
        if raw.startswith("## "):
            doc.heading(raw[3:], 2)
        elif raw.startswith("### "):
            doc.heading(raw[4:], 3)
        elif raw.startswith("- "):
            doc.paragraph(raw[2:], 10.2, bullet=True)
        else:
            paragraph = re.sub(r"`([^`]+)`", r"\1", raw)
            paragraph = paragraph.replace("**", "")
            doc.paragraph(paragraph, 10.4)

    flush_table()
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT.write_bytes(doc.finish())
    print(OUTPUT)


if __name__ == "__main__":
    markdown_to_pdf()
