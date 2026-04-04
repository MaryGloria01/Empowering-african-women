# Empowering African Women (EAW) — Online Learning Portal

> **DBTWTEi Nigeria** | Equipping women across Africa with vocational, professional and life skills through free, accessible online education.

[![Live Site](https://img.shields.io/badge/Live%20Site-Visit-blue?style=for-the-badge)](https://empoweringafricanwomen.com)
[![HTML](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)](https://developer.mozilla.org/en-US/docs/Web/HTML)
[![CSS](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)](https://developer.mozilla.org/en-US/docs/Web/CSS)
[![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)

---

## About the Project

**Empowering African Women (EAW)** is a free online learning platform built for DBTWTEi Nigeria. The platform provides African women with access to professional courses in vocational skills, business, health, and personal development — completely free of charge.

The website is built as a fully static, no-backend application using vanilla HTML, CSS, and JavaScript with `localStorage` for user session management. No external database or server-side language is required.

---

## Live Features

### Public Pages
| Page | Description |
|------|-------------|
| `index.html` | Homepage — hero, how it works, impact numbers, testimonials, tutors |
| `courses.html` | Full course catalogue |
| `about.html` | Organisation story and mission |
| `contact.html` | Contact form and organisation details |
| `stories.html` | Student success stories |
| `signup.html` | Student registration |
| `login.html` | Student login |
| `tutor-apply.html` | Tutor application form |

### Course Pages (All Free)
| Course | File |
|--------|------|
| Female Mechanic Incubator | `course-mechanic.html` |
| Baking & Cooking Essentials | `course-baking.html` |
| Social & Business Etiquette for Professionals | `course-etiquette.html` |
| English Language & Communication | `course-english.html` |
| Digital Skills & Computer Literacy | `course-digital-skills.html` |
| Entrepreneurship & Business Development | `course-entrepreneurship.html` |
| Personal Finance & Money Management | `course-finance.html` |
| Health & Wellness | `course-health.html` |
| Soft Skills & Emotional Intelligence | `course-soft-skills.html` |
| Coding & Tech Fundamentals | `course-coding.html` |
| Sewing & Fashion Design | `course-sewing.html` |

### Dashboards
| Dashboard | File |
|-----------|------|
| Student Dashboard | `student-dashboard.html` |
| Tutor Dashboard | `tutor-dashboard.html` |
| Admin Dashboard | `admin-dashboard.html` |

### Quizzes
Each course has a dedicated quiz (`quiz-[course].html`) for knowledge assessment.

---

## Tech Stack

| Layer | Technology |
|-------|------------|
| Markup | HTML5 (semantic) |
| Styling | Custom CSS3 — Premium Design System v3 |
| Logic | Vanilla JavaScript (ES6+) |
| Auth & Data | Browser `localStorage` |
| Fonts | Source Serif 4, Source Sans 3, Raleway (Google Fonts) |
| Hosting | Hostinger Shared Hosting |

---

## Design System

The site uses a custom **Premium Design System v3** (`styles.css`) with:

- **Color Palette:** Navy `#0F172A` · Blue `#1D4ED8` · Gold `#D97706` · Teal `#0F766E` · Emerald `#059669`
- **Typography:** Source Serif 4 (headings), Source Sans 3 (body), Raleway (decorative)
- **Components:** Glass-morphism navbar, hero gradients, card grids, testimonials, impact counters
- **Responsive:** Mobile-first layout with hamburger navigation

---

## Project Structure

```
eaw-v2/
├── index.html                    # Homepage
├── courses.html                  # Course catalogue
├── about.html                    # About page
├── contact.html                  # Contact page
├── stories.html                  # Success stories
├── login.html                    # Login
├── signup.html                   # Registration
├── tutor-apply.html              # Tutor application
├── student-dashboard.html        # Student portal
├── tutor-dashboard.html          # Tutor portal
├── admin-dashboard.html          # Admin portal
├── course-[name].html            # Individual course pages (×11)
├── quiz-[name].html              # Course quizzes (×11)
├── styles.css                    # Global design system
├── app.js                        # Shared JS (navbar, animations)
├── Video_courses/                # Course video content
│   └── Introduction_To_Etiquette.mp4
│   └── Empowering_Women_in_Africa_Female_Mechanic_Incubator.mp4
├── BAKING AND COOKING - Tutor Ebere/  # Baking course materials
├── Pictures/                     # Organisation photo gallery
└── eaw_logo.png                  # Brand assets
```

> **Note:** Large video files (`Auto_Care_101.mp4`, etc.) and the admin management portal are excluded from this repository. They are deployed directly to the server.

---

## Management Portal

A secure **Admin Management Portal** has been built for internal use by authorised EAW personnel. It includes:

- Admin authentication with brute-force lockout protection
- Student and tutor management
- Course and content oversight
- Platform analytics dashboard

This portal is **intentionally not included** in this public repository for security reasons. It is deployed separately and directly to the hosting server.

---

## Getting Started (Local Development)

No build tools or server required. Simply open any `.html` file in your browser:

```bash
# Clone the repository
git clone https://github.com/MaryGloria01/Empowering-african-women.git

# Navigate into the folder
cd Empowering-african-women

# Open in browser (or use VS Code Live Server)
open index.html
```

---

## Deployment

The site is hosted on **Hostinger Shared Hosting**.

For updates:
1. Push changes to this GitHub repository
2. Upload updated files to Hostinger via File Manager or FTP
3. Large assets (videos, admin portal) are uploaded directly via Hostinger File Manager

---

## Organisation

**DBTWTEi Nigeria** — Empowering African Women Initiative

- **Mission:** Bridge the skills and opportunities gap for women in Africa through free, quality education
- **Reach:** Students across Nigeria and the broader African continent
- **Contact:** [ogochukwumarygloria16@gmail.com](mailto:ogochukwumarygloria16@gmail.com)

---

## License

© 2026 DBTWTEi Nigeria — Empowering African Women. All rights reserved.

This project is proprietary. The code, design, and content may not be reproduced, distributed, or used without explicit written permission from DBTWTEi Nigeria.
