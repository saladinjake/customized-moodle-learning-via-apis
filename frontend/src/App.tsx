import React, { useState, useEffect } from 'react';
import './index.css';

// Mock Data representing the API response from our headless Moodle Global Renderer Moodle override
const API_DATA = {
  page: {
    title: 'Dashboard',
    heading: 'Welcome back, Student',
  },
  site: {
    fullname: 'Advanced Agentic University LMS',
  },
  courses: [
    { id: 1, title: 'Advanced React Architecture', progress: 75, lastAccessed: '2 hours ago' },
    { id: 2, title: 'API-First Microservices', progress: 42, lastAccessed: 'Yesterday' },
    { id: 3, title: 'UI/UX Design Systems', progress: 91, lastAccessed: 'Just now' },
  ]
};

function App() {
  const [data, setData] = useState<any>(null);

  useEffect(() => {
    // Simulating Axios fetch from 'https://moodle.dev/index.php'
    // which now returns JSON thanks to our `theme_headless` override!
    setTimeout(() => {
      setData(API_DATA);
    }, 800);
  }, []);

  return (
    <div className="app-container">
      {/* Sidebar representing global navigation */}
      <aside className="sidebar">
        <h2>{data ? data.site.fullname : 'Loading...'}</h2>
        <nav style={{ marginTop: '40px' }}>
          <div className="nav-link active">Dashboard</div>
          <div className="nav-link">My Courses</div>
          <div className="nav-link">Grades</div>
          <div className="nav-link">Settings</div>
        </nav>
      </aside>

      {/* Main LMS Workspace */}
      <main className="main-content">
        <header className="header animate-fade-in">
          <div>
            <h1 style={{ marginBottom: '8px' }}>{data ? data.page.heading : 'Connecting to Core...'}</h1>
            <p style={{ color: 'var(--text-muted)' }}>Here's what is happening with your learning right now.</p>
          </div>
          <div className="user-profile">
            <div className="avatar">
              {/* Fallback image if generator delays */}
              <img src="https://i.pravatar.cc/150?img=11" alt="Student Avatar" />
            </div>
          </div>
        </header>

        {/* Hero Banner displaying the generated AI art */}
        <section className="hero-banner animate-fade-in" style={{ animationDelay: '0.1s' }}>
          <img src="/dashboard_hero_bg.png" alt="Dashboard Hero Artwork" onError={(e) => {
            // Failsafe styling if the generated image hasn't landed in public/ yet
            (e.target as any).style.display = 'none';
            (e.target as any).parentElement.style.background = 'linear-gradient(135deg, var(--primary), var(--secondary))';
          }} />
          <div className="hero-overlay">
            <h2 style={{ fontSize: '32px', color: '#fff',WebkitTextFillColor: '#fff' }}>Spring 2026 Semester</h2>
            <p style={{ color: '#cbd5e1', marginTop: '8px' }}>You have 3 assignments due this week.</p>
          </div>
        </section>

        {/* Enrolled Courses Grid */}
        <h3 className="animate-fade-in" style={{ animationDelay: '0.2s', fontSize: '24px' }}>Continue Learning</h3>
        
        <div className="courses-grid">
          {data ? data.courses.map((course: any, idx: number) => (
            <div className="glass glass-panel animate-fade-in" key={course.id} style={{ animationDelay: `${0.3 + (idx * 0.1)}s` }}>
              <div style={{ height: '140px', background: 'rgba(255,255,255,0.05)', borderRadius: '8px', marginBottom: '20px' }} />
              <h4 className="course-title">{course.title}</h4>
              <div className="course-meta">
                <span>{course.progress}% Complete</span>
                <span>{course.lastAccessed}</span>
              </div>
              <div className="progress-bg">
                <div className="progress-fill" style={{ width: `${course.progress}%` }}></div>
              </div>
            </div>
          )) : (
            // Skeletons
            [1, 2, 3].map(i => (
              <div key={i} className="glass glass-panel animate-fade-in" style={{ opacity: 0.5 }}>
                 <div style={{ height: '140px', background: 'rgba(255,255,255,0.05)', borderRadius: '8px', marginBottom: '20px' }} />
                 <div style={{ width: '60%', height: '24px', background: 'rgba(255,255,255,0.1)', borderRadius: '4px', marginBottom: '16px' }} />
                 <div style={{ width: '100%', height: '6px', background: 'rgba(255,255,255,0.1)', borderRadius: '10px' }} />
              </div>
            ))
          )}
        </div>
      </main>
    </div>
  );
}

export default App;
