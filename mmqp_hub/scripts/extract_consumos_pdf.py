"""Extract every native text line and table column; parsing/validation stays in PHP."""

import json
import re
import sys

import pdfplumber


def extract(path):
    pages = []
    with pdfplumber.open(path) as pdf:
        if not pdf.pages or len(pdf.pages) > 100:
            raise ValueError("El informe debe tener entre 1 y 100 páginas.")
        for number, page in enumerate(pdf.pages, 1):
            lines = []
            boundaries = None
            for line in page.extract_text_lines(layout=False, strip=True, return_chars=True):
                chars = line.pop("chars")
                raw = line["text"]
                words = page.crop((0, max(0, line["top"] - 1), page.width,
                                   min(page.height, line["bottom"] + 1))).extract_words()
                if re.match(r"^INGREDIENTE\s+CONS\.", raw, re.I):
                    labels = {word["text"].upper(): word["x0"] for word in words}
                    if all(label in labels for label in ["CONS.", "UNIDAD", "VALOR"]):
                        boundaries = [labels["CONS."] - 12, labels["UNIDAD"] - 12,
                                      labels["VALOR"] - 12]
                columns = None
                if boundaries:
                    cells = [[], [], [], []]
                    for word in words:
                        index = sum(word["x0"] >= boundary for boundary in boundaries)
                        cells[index].append(word["text"])
                    columns = dict(zip(["name", "quantity", "unit", "value"],
                                       [" ".join(cell) for cell in cells]))
                lines.append({"text": raw, "x0": round(line["x0"], 3),
                              "top": round(line["top"], 3),
                              "bold": bool(chars) and all("Bold" in c["fontname"] for c in chars),
                              "columns": columns})
            pages.append({"number": number, "lines": lines})
    return pages


if __name__ == "__main__":
    try:
        # ASCII JSON remains lossless and avoids Windows console codepage conversion.
        print(json.dumps(extract(sys.argv[1]), ensure_ascii=True, separators=(",", ":")))
    except Exception as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
