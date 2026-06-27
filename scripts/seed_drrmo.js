const fs = require('fs');

function escSqlString(s) {
  return String(s).replace(/\\/g, '\\\\').replace(/'/g, "''");
}

function main() {
  const jsonPath = 'c:/xampp/htdocs/ConnectDRRM/config/data/zamboanga_del_sur_complete.json';
  const raw = fs.readFileSync(jsonPath, 'utf8');
  const j = JSON.parse(raw);

  const province = j.province || 'Zamboanga del Sur';

  // Requirements:
  // - Insert ALL municipalities/cities except any "Zamboanga del Sur" province entry (not present as a row in JSON)
  // - Exclude "Zamboanga City" (not part of Zamboanga del Sur province LGUs)
  // - First row should be the PDRRMO
  const rows = [];
  rows.push({
    name: `PDRRMO - ${province}`,
    type: 'PDRRMO',
    location: province,
    lat: null,
    lng: null,
  });

  for (const m of j.municipalities || []) {
    if (!m || !m.name) continue;
    const name = String(m.name).trim();
    if (name.toLowerCase() === 'zamboanga city') continue;

    const kind = String(m.type || '').toLowerCase();
    const type = kind === 'city' ? 'CDRRMO' : 'MDRRMO';
    const coords = Array.isArray(m.coordinates) ? m.coordinates : [null, null];
    const lat = coords[0] ?? null;
    const lng = coords[1] ?? null;

    rows.push({ name, type, location: province, lat, lng });
  }

  let out = '';
  out += 'START TRANSACTION;\n';
  out += "USE `connectdrrm`;\n";
  out += 'INSERT IGNORE INTO `drrmo` (`name`,`type`,`location`,`latitude`,`longitude`) VALUES\n';
  out +=
    rows
      .map((r) => {
        const lat = r.lat == null ? 'NULL' : Number(r.lat);
        const lng = r.lng == null ? 'NULL' : Number(r.lng);
        return `('${escSqlString(r.name)}','${escSqlString(r.type)}','${escSqlString(r.location)}',${lat},${lng})`;
      })
      .join(',\n') + ';\n';
  out += 'COMMIT;\n';

  process.stdout.write(out);
}

main();

