const fs = require('fs');

function processFile(targetFile) {
    let content = fs.readFileSync(targetFile, 'utf8');
    
    // Convert ANY NFD to NFC
    let normalized = content.normalize('NFC');
    
    fs.writeFileSync(targetFile, normalized, 'utf8');
    console.log("Processed: " + targetFile);
}

const files = [
    'index.php',
    'includes/header.php',
    'includes/footer.php',
    'community.php',
    'services.php',
    'service_detail.php'
];

files.forEach(f => {
    if(fs.existsSync(f)) {
        processFile(f);
    }
});
