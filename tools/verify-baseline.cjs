'use strict';
// Package-only verification. Does not load WordPress or execute plugin PHP.
const fs = require('node:fs'), crypto = require('node:crypto'), zlib = require('node:zlib'), vm = require('node:vm');
const assert = (ok, message) => { if (!ok) throw Error(message); };
const zip = fs.readFileSync('siteengine-call-bridge-0.1.0.zip');
assert(zip.length === 52306, 'Archive size mismatch');
assert(crypto.createHash('sha256').update(zip).digest('hex') === '5a1b28ac80dfcd2b6e09cd34bdc9b2d126774b588a501d852fe01f4bdd563fb8', 'Archive SHA-256 mismatch');
function crc32(bytes) { let crc = 0xffffffff; for (const b of bytes) { crc ^= b; for (let i = 0; i < 8; i++) crc = (crc >>> 1) ^ ((crc & 1) ? 0xedb88320 : 0); } return (crc ^ 0xffffffff) >>> 0; }
let end = zip.length - 22;
while (end >= 0 && zip.readUInt32LE(end) !== 0x06054b50) end--;
assert(end >= 0, 'Missing ZIP end record');
const entries = zip.readUInt16LE(end + 10), files = new Map();
let pos = zip.readUInt32LE(end + 16);
for (let i = 0; i < entries; i++) {
 assert(zip.readUInt32LE(pos) === 0x02014b50, 'Bad central record');
 const method = zip.readUInt16LE(pos + 10), crc = zip.readUInt32LE(pos + 16), packed = zip.readUInt32LE(pos + 20), size = zip.readUInt32LE(pos + 24);
 const len = zip.readUInt16LE(pos + 28), extra = zip.readUInt16LE(pos + 30), comment = zip.readUInt16LE(pos + 32), offset = zip.readUInt32LE(pos + 42);
 const name = zip.subarray(pos + 46, pos + 46 + len).toString();
 assert(name.startsWith('siteengine-call-bridge/') && !name.split('/').includes('..'), 'Unsafe package path');
 assert(zip.readUInt32LE(offset) === 0x04034b50, 'Bad local record');
 const start = offset + 30 + zip.readUInt16LE(offset + 26) + zip.readUInt16LE(offset + 28);
 const compressed = zip.subarray(start, start + packed);
 assert(method === 0 || method === 8, 'Unsupported compression');
 const body = method === 8 ? zlib.inflateRawSync(compressed) : compressed;
 assert(body.length === size && crc32(body) === crc, 'CRC/size failure: ' + name);
 if (!name.endsWith('/')) {
  const rel = name.slice('siteengine-call-bridge/'.length);
  assert(!files.has(rel), 'Duplicate path'); files.set(rel, body);
  assert(fs.readFileSync(rel).equals(body), 'Checkout differs from package: ' + rel);
 }
 pos += 46 + len + extra + comment;
}
const manifest = files.get('RELEASE-MANIFEST.sha256').toString().trim().split(/\r?\n/);
assert(manifest.length === 45 && files.size === 46, 'Unexpected file/manifest count');
for (const line of manifest) {
 const match = line.match(/^([a-f0-9]{64})\s+\.\/(.+)$/); assert(match, 'Bad manifest line');
 assert(files.has(match[2]), 'Missing manifest file');
 assert(crypto.createHash('sha256').update(files.get(match[2])).digest('hex') === match[1], 'Manifest hash mismatch');
}
const php = [...files].filter(([name]) => name.endsWith('.php'));
assert(php.length === 42 && php.every(([, body]) => body.subarray(0, 5).toString() === '<?php'), 'PHP count/opening mismatch');
let js = files.get('src/Frontend/DniSnippet.php').toString().split('<script id="secb-dni-script">')[1].split('</script>')[0], replacements = 0;
js = js.replace(/<\?php[\s\S]*?\?>/g, () => ['"https://example.invalid/"', '[]', '90'][replacements++]);
assert(replacements === 3, 'Unexpected DNI placeholders'); new vm.Script(js);
JSON.parse(files.get('composer.json'));
console.log('PASS archive SHA-256/size; ZIP CRC and decompression (' + entries + ' entries); 45 manifest hashes; 46 byte-identical checkout files; 42 clean PHP openings; composer JSON; DNI JS syntax with 3 inert substitutions.');
console.log('No PHP syntax/load, WordPress, database, browser or provider acceptance is established.');
