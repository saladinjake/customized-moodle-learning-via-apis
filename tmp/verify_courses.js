const https = require('https');

const BASE_URL = 'https://lumina-moodle-backend.onrender.com/local/api/index.php';

async function get(url) {
    return new Promise((resolve, reject) => {
        https.get(url, (res) => {
            let data = '';
            res.on('data', (chunk) => data += chunk);
            res.on('end', () => {
                try {
                    resolve(JSON.parse(data));
                } catch (e) {
                    reject(e);
                }
            });
        }).on('error', reject);
    });
}

async function verify() {
    console.log('Fetching courses list...');
    const coursesRes = await get(`${BASE_URL}?action=public_get_courses&limit=100`);
    
    if (!coursesRes || !coursesRes.data) {
        console.error('Failed to fetch courses');
        return;
    }

    const courses = coursesRes.data;
    console.log(`Found ${courses.length} courses to verify.`);

    let emptyCount = 0;
    let successCount = 0;

    for (const course of courses) {
        process.stdout.write(`Checking course ID ${course.id}: `);
        try {
            const detail = await get(`${BASE_URL}?action=public_get_course_detail&courseid=${course.id}`);
            
            if (detail && detail.data && detail.data.tree && detail.data.tree.length > 0) {
                let moduleCount = 0;
                detail.data.tree.forEach(section => {
                    moduleCount += (section.items ? section.items.length : 0);
                });
                
                if (moduleCount > 0) {
                    console.log(`PASS (${detail.data.tree.length} sections, ${moduleCount} modules)`);
                    successCount++;
                } else {
                    console.log('FAIL (No modules found)');
                    emptyCount++;
                }
            } else {
                console.log('FAIL (No sections found)');
                emptyCount++;
            }
        } catch (e) {
            console.log(`ERROR (${e.message})`);
            emptyCount++;
        }
    }

    console.log('\n--- VERIFICATION SUMMARY ---');
    console.log(`Total Courses Checked: ${courses.length}`);
    console.log(`Courses with Structure: ${successCount}`);
    console.log(`Courses Empty: ${emptyCount}`);
}

verify();
