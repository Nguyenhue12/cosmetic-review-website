const fs = require('fs');
const path = require('path');

function walkDir(dir) {
    fs.readdirSync(dir).forEach(file => {
        let fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            if (file !== 'vendor' && file !== 'node_modules' && file !== '.git') {
                walkDir(fullPath);
            }
        } else if (fullPath.endsWith('.php')) {
            let content = fs.readFileSync(fullPath, 'utf8');
            let normalized = content.normalize('NFC');
            if (content !== normalized) {
                fs.writeFileSync(fullPath, normalized, 'utf8');
                console.log('Normalized: ' + fullPath);
            }
        }
    });
}
walkDir('.');
console.log('Done');
