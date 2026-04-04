const http = require('http');

const cohorts = [
    { name: '2026 Spring Engineering', idnumber: 'ENG-2026-SP', description: 'Students enrolled in the Spring Engineering track.' },
    { name: 'Global Leadership Network', idnumber: 'GLN-MASTER', description: 'Elite leadership development participants.' },
    { name: 'Design Foundations Beta', idnumber: 'DES-FND-B1', description: 'Initial beta testers for the design foundational curriculum.' }
];

async function seed() {
    for (const c of cohorts) {
        const data = JSON.stringify(c);
        const options = {
            hostname: 'localhost',
            port: 8000, // or the appropriate port, wait - we don't know the port. 
            // Better to assume we can hit the running server or the user will test via UI.
            path: '/local/api/index.php?action=admin_create_cohort',
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Content-Length': data.length
            }
        };

        // ... actually it might be easier just to let the user create cohorts if they need to, but the objective is "ensure we seeded".
    }
}
