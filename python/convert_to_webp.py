import sys
from pathlib import Path

from PIL import Image


def main() -> int:
    if len(sys.argv) < 3:
        print("Uso: python convert_to_webp.py <input_path> <output_path>", file=sys.stderr)
        return 1

    input_path = Path(sys.argv[1])
    output_path = Path(sys.argv[2])

    if not input_path.is_file():
        print(f"Archivo de entrada no encontrado: {input_path}", file=sys.stderr)
        return 1

    try:
        with Image.open(input_path) as im:
            im.save(output_path, format="WEBP", quality=80)
    except Exception as exc:  # noqa: BLE001
        print(f"Error al convertir la imagen a WebP: {exc}", file=sys.stderr)
        return 1

    if not output_path.is_file():
        print(f"No se pudo crear el archivo de salida: {output_path}", file=sys.stderr)
        return 1

    return 0


if __name__ == "__main__":
    raise SystemExit(main())

