#!/usr/bin/env python3
"""Export a creature appearance from OTClient's installed 12.81+ assets.

The OTClient client uses an appearances.dat protobuf catalog and LZMA-compressed
sprite sheets; it does not store one GIF per monster. This small dependency-free
exporter reads those same assets and writes a transparent PNG suitable for the
website. It intentionally uses the looktype, not a display name.
"""

import argparse
import json
import lzma
import struct
import sys
import zlib
from pathlib import Path


SPRITE_LAYOUTS = (
    (32, 32), (32, 64), (64, 32), (64, 64), (32, 96), (32, 128),
    (32, 192), (32, 384), (64, 96), (64, 128), (64, 192), (64, 384),
    (96, 32), (96, 64), (96, 96), (96, 128), (96, 192), (96, 384),
    (128, 32), (128, 64), (128, 96), (128, 128), (128, 192), (128, 384),
    (192, 32), (192, 64), (192, 96), (192, 128), (192, 192), (192, 384),
    (384, 32), (384, 64), (384, 96), (384, 128), (384, 192), (384, 384),
)
SHEET_SIZE = 384


def read_varint(data, offset):
    value = shift = 0
    while True:
        if offset >= len(data):
            raise ValueError('Truncated protobuf varint')
        byte = data[offset]
        offset += 1
        value |= (byte & 0x7F) << shift
        if not byte & 0x80:
            return value, offset
        shift += 7
        if shift > 63:
            raise ValueError('Invalid protobuf varint')


def protobuf_fields(data):
    offset = 0
    while offset < len(data):
        key, offset = read_varint(data, offset)
        field, wire = key >> 3, key & 0x07
        if wire == 0:
            value, offset = read_varint(data, offset)
        elif wire == 1:
            value, offset = data[offset:offset + 8], offset + 8
        elif wire == 2:
            length, offset = read_varint(data, offset)
            value, offset = data[offset:offset + length], offset + length
        elif wire == 5:
            value, offset = data[offset:offset + 4], offset + 4
        else:
            raise ValueError('Unsupported protobuf wire type {}'.format(wire))
        yield field, wire, value


def unpack_varints(data):
    offset = 0
    values = []
    while offset < len(data):
        value, offset = read_varint(data, offset)
        values.append(value)
    return values


def parse_sprite_info(data):
    parsed = {'width': 1, 'height': 1, 'depth': 1, 'layers': 1, 'sprites': []}
    for field, wire, value in protobuf_fields(data):
        if field == 1:
            parsed['width'] = value
        elif field == 2:
            parsed['height'] = value
        elif field == 3:
            parsed['depth'] = value
        elif field == 4:
            parsed['layers'] = value
        elif field == 5:
            parsed['sprites'].extend(unpack_varints(value) if wire == 2 else [value])
    return parsed


def find_initial_sprite_info(appearances, looktype, category):
    category_field = 2 if category == 'outfit' else 1
    for field, wire, appearance in protobuf_fields(appearances):
        if field != category_field or wire != 2:
            continue
        appearance_id = None
        groups = []
        for child_field, child_wire, child_value in protobuf_fields(appearance):
            if child_field == 1:
                appearance_id = child_value
            elif child_field == 2 and child_wire == 2:
                fixed_group = None
                sprite_info = None
                for group_field, group_wire, group_value in protobuf_fields(child_value):
                    if group_field == 1:
                        fixed_group = group_value
                    elif group_field == 3 and group_wire == 2:
                        sprite_info = parse_sprite_info(group_value)
                if sprite_info is not None:
                    groups.append((fixed_group, sprite_info))
        if appearance_id == looktype:
            # Fixed frame group 0 is the client's initial/static pose.
            for fixed_group, sprite_info in groups:
                if fixed_group == 0:
                    return sprite_info
            if groups:
                return groups[0][1]
    raise ValueError('{} appearance {} was not found in appearances.dat'.format(category, looktype))


def decode_sheet(path):
    compressed = path.read_bytes()
    offset = 0
    while offset < len(compressed) and compressed[offset] == 0:
        offset += 1
    if compressed[offset:offset + 5] != b'\x70\x0a\xfa\x80\x24':
        raise ValueError('{} does not have an OTClient sprite-sheet header'.format(path.name))
    offset += 5
    while compressed[offset] & 0x80:
        offset += 1
    offset += 1  # 7-bit encoded CIP uncompressed-size field
    properties = compressed[offset]
    offset += 1
    dictionary_size = int.from_bytes(compressed[offset:offset + 4], 'little')
    offset += 4 + 8  # dictionary and CIP compressed-size field
    filters = [{
        'id': lzma.FILTER_LZMA1,
        'dict_size': dictionary_size,
        'lc': properties % 9,
        'lp': (properties // 9) % 5,
        'pb': properties // 45,
    }]
    decoded = lzma.decompress(compressed[offset:], format=lzma.FORMAT_RAW, filters=filters)
    bmp_offset = int.from_bytes(decoded[10:14], 'little')
    pixel_data = decoded[bmp_offset:bmp_offset + SHEET_SIZE * SHEET_SIZE * 4]
    if len(pixel_data) != SHEET_SIZE * SHEET_SIZE * 4:
        raise ValueError('{} has an invalid decoded sprite-sheet size'.format(path.name))

    # Client code converts BGRA to RGBA, treats magenta as transparent, then
    # vertically flips the bitmap. Mirror those exact operations here.
    rgba = bytearray(len(pixel_data))
    for source_y in range(SHEET_SIZE):
        target_y = SHEET_SIZE - 1 - source_y
        for x in range(SHEET_SIZE):
            source = (source_y * SHEET_SIZE + x) * 4
            target = (target_y * SHEET_SIZE + x) * 4
            blue, green, red, alpha = pixel_data[source:source + 4]
            rgba[target:target + 4] = bytes((red, green, blue, 0 if (red, green, blue) == (255, 0, 255) else alpha))
    return rgba


def crop_sprite(sheet, descriptor, sprite_id):
    width, height = SPRITE_LAYOUTS[descriptor['spritetype']]
    first_id = descriptor['firstspriteid']
    sprite_offset = sprite_id - first_id
    columns = SHEET_SIZE // width
    row, column = divmod(sprite_offset, columns)
    if sprite_offset < 0 or (row + 1) * height > SHEET_SIZE:
        raise ValueError('Sprite {} is outside {}'.format(sprite_id, descriptor['file']))
    result = bytearray(width * height * 4)
    for y in range(height):
        source = ((row * height + y) * SHEET_SIZE + column * width) * 4
        target = y * width * 4
        result[target:target + width * 4] = sheet[source:source + width * 4]
    return width, height, result


def alpha_over(base, overlay):
    for offset in range(0, len(base), 4):
        alpha = overlay[offset + 3]
        if alpha == 0:
            continue
        if alpha == 255 or base[offset + 3] == 0:
            base[offset:offset + 4] = overlay[offset:offset + 4]
            continue
        inverse = 255 - alpha
        base_alpha = base[offset + 3]
        output_alpha = alpha + base_alpha * inverse // 255
        for channel in range(3):
            base[offset + channel] = (overlay[offset + channel] * alpha + base[offset + channel] * inverse) // 255
        base[offset + 3] = output_alpha


def outfit_color(index):
    # Same HSI palette conversion used by OTClient's Outfit::getColor().
    if index < 0 or index >= 133:
        index = 0
    hue_step, band = index % 19, index // 19
    if hue_step == 0:
        value = int((1 - index / 133) * 255)
        return value, value, value
    hue = hue_step / 18.0
    saturation, intensity = ((0.25, 1.00), (0.25, 0.75), (0.50, 0.75), (0.667, 0.75), (1.00, 1.00), (1.00, 0.75), (1.00, 0.50))[band]
    if hue < 1 / 6:
        red, blue = intensity, intensity * (1 - saturation)
        green = blue + (intensity - blue) * 6 * hue
    elif hue < 2 / 6:
        green, blue = intensity, intensity * (1 - saturation)
        red = green - (intensity - blue) * (6 * hue - 1)
    elif hue < 3 / 6:
        green, red = intensity, intensity * (1 - saturation)
        blue = red + (intensity - red) * (6 * hue - 2)
    elif hue < 4 / 6:
        blue, red = intensity, intensity * (1 - saturation)
        green = blue - (intensity - red) * (6 * hue - 3)
    elif hue < 5 / 6:
        blue, green = intensity, intensity * (1 - saturation)
        red = green + (intensity - green) * (6 * hue - 4)
    else:
        red, green = intensity, intensity * (1 - saturation)
        blue = red - (intensity - green) * (6 * hue - 5)
    return tuple(int(component * 255) for component in (red, green, blue))


def multiply_mask(base, source, source_color, tint):
    for offset in range(0, len(base), 4):
        if source[offset + 3] == 0 or tuple(source[offset:offset + 3]) != source_color:
            continue
        alpha = source[offset + 3] / 255.0
        for channel in range(3):
            factor = tint[channel] / 255.0
            base[offset + channel] = int(base[offset + channel] * ((1 - alpha) + alpha * factor))


def png_chunk(kind, data):
    return struct.pack('>I', len(data)) + kind + data + struct.pack('>I', zlib.crc32(kind + data) & 0xFFFFFFFF)


def write_png(path, width, height, rgba):
    scanlines = b''.join(b'\x00' + bytes(rgba[y * width * 4:(y + 1) * width * 4]) for y in range(height))
    payload = b'\x89PNG\r\n\x1a\n'
    payload += png_chunk(b'IHDR', struct.pack('>IIBBBBB', width, height, 8, 6, 0, 0, 0))
    payload += png_chunk(b'IDAT', zlib.compress(scanlines, 9))
    payload += png_chunk(b'IEND', b'')
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(payload)


def main():
    parser = argparse.ArgumentParser(description='Export an official OTClient outfit sprite to PNG.')
    parser.add_argument('--assets', required=True, type=Path, help='OTClient data/things/<version> directory')
    parser.add_argument('--looktype', required=True, type=int)
    parser.add_argument('--output', required=True, type=Path)
    parser.add_argument('--category', choices=('outfit', 'object'), default='outfit')
    parser.add_argument('--direction', type=int, default=2, choices=range(4), help='0=N, 1=E, 2=S, 3=W (default: S)')
    parser.add_argument('--head', type=int, default=0)
    parser.add_argument('--body', type=int, default=0)
    parser.add_argument('--legs', type=int, default=0)
    parser.add_argument('--feet', type=int, default=0)
    parser.add_argument('--addons', type=int, default=0)
    args = parser.parse_args()

    catalog_file = args.assets / 'catalog-content.json'
    if not catalog_file.is_file():
        raise FileNotFoundError('Missing {}'.format(catalog_file))
    catalog = json.loads(catalog_file.read_text())
    appearances_entry = next((entry for entry in catalog if entry.get('type') == 'appearances'), None)
    if appearances_entry is None:
        raise ValueError('catalog-content.json has no appearances entry')
    sprite_descriptors = [entry for entry in catalog if entry.get('type') == 'sprite']
    sprite_info = find_initial_sprite_info((args.assets / appearances_entry['file']).read_bytes(), args.looktype, args.category)
    width, height, depth, layers = (sprite_info[key] for key in ('width', 'height', 'depth', 'layers'))
    if args.direction >= width:
        raise ValueError('Looktype {} has only {} directions'.format(args.looktype, width))

    sheet_cache = {}

    def image_for(sprite_id):
        descriptor = next((entry for entry in sprite_descriptors if entry['firstspriteid'] <= sprite_id <= entry['lastspriteid']), None)
        if descriptor is None:
            raise ValueError('No catalog sheet contains sprite {}'.format(sprite_id))
        sheet = sheet_cache.setdefault(descriptor['file'], decode_sheet(args.assets / descriptor['file']))
        return crop_sprite(sheet, descriptor, sprite_id)

    def index_for(layer, y_pattern):
        return ((y_pattern * width + args.direction) * layers) + layer

    output = None
    active_addon_patterns = [0] + [pattern for pattern in range(1, height) if args.addons & (1 << (pattern - 1))]
    for y_pattern in active_addon_patterns:
        base_index = index_for(0, y_pattern)
        if base_index >= len(sprite_info['sprites']):
            continue
        image_width, image_height, base = image_for(sprite_info['sprites'][base_index])
        if output is None:
            output = bytearray(base)
        else:
            alpha_over(output, base)
        if args.category == 'outfit' and layers > 1:
            mask_index = index_for(1, y_pattern)
            if mask_index < len(sprite_info['sprites']):
                mask_width, mask_height, mask = image_for(sprite_info['sprites'][mask_index])
                if (mask_width, mask_height) != (image_width, image_height):
                    raise ValueError('Mask dimensions do not match the base sprite')
                multiply_mask(output, mask, (255, 255, 0), outfit_color(args.head))
                multiply_mask(output, mask, (255, 0, 0), outfit_color(args.body))
                multiply_mask(output, mask, (0, 255, 0), outfit_color(args.legs))
                multiply_mask(output, mask, (0, 0, 255), outfit_color(args.feet))
    if output is None:
        raise ValueError('Looktype {} contains no renderable initial sprite'.format(args.looktype))
    write_png(args.output, image_width, image_height, output)
    print('{} -> {} ({}x{}, looktype {})'.format(args.assets, args.output, image_width, image_height, args.looktype))


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print('Export failed: {}'.format(error), file=sys.stderr)
        sys.exit(1)
