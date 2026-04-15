# 🔗 Frontend Integration Guide

How to connect your HTML/JavaScript frontend with the Skill Swap Backend API.

---

## 📋 Table of Contents
1. [API Configuration](#api-configuration)
2. [Authentication Setup](#authentication-setup)
3. [Making API Calls](#making-api-calls)
4. [Code Examples](#code-examples)
5. [Error Handling](#error-handling)

---

## 🔧 API Configuration

### Update Your Frontend URLs

In each HTML file, update the base API URL:

```javascript
// Add at the top of your script section
const API_URL = 'http://localhost:8000/api';
```

**For Production:**
```javascript
const API_URL = 'https://api.skillswap.com/api';
```

---

## 🔐 Authentication Setup

### 1. Update Login Form (`login.html`)

Replace the login form submission handler:

```javascript
const loginForm = document.getElementById('loginForm');

loginForm.addEventListener('submit', async function(e) {
  e.preventDefault();
  
  const email = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value;
  
  try {
    const response = await fetch(`${API_URL}/auth/login`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ email, password })
    });
    
    const data = await response.json();
    
    if (data.success) {
      // Store token and user info
      localStorage.setItem('token', data.data.token);
      localStorage.setItem('user', JSON.stringify(data.data.user));
      localStorage.setItem('isLoggedIn', 'true');
      
      // Redirect based on role
      if (data.data.user.role === 'admin') {
        window.location.href = 'dashboard.html';
      } else {
        window.location.href = 'index.html';
      }
    } else {
      showError(data.message);
    }
  } catch (error) {
    console.error('Login error:', error);
    showError('An error occurred. Please try again.');
  }
});

function showError(message) {
  const errorDiv = document.getElementById('errorMessage');
  errorDiv.textContent = message;
  errorDiv.classList.add('show');
  setTimeout(() => errorDiv.classList.remove('show'), 3000);
}
```

---

### 2. Update Signup Form (`signup.html`)

```javascript
const signupForm = document.getElementById('signupForm');

signupForm.addEventListener('submit', async function(e) {
  e.preventDefault();
  
  const formData = {
    first_name: document.querySelector('input[placeholder="Full Name"]').value.split(' ')[0],
    last_name: document.querySelector('input[placeholder="Full Name"]').value.split(' ').slice(1).join(' '),
    phone: document.querySelector('input[placeholder="Phone Number"]').value,
    email: document.querySelector('input[placeholder="Email Address"]').value,
    password: document.querySelectorAll('input[type="password"]')[0].value
  };
  
  const confirmPassword = document.querySelectorAll('input[type="password"]')[1].value;
  
  // Check password match
  if (formData.password !== confirmPassword) {
    alert('Passwords do not match!');
    return;
  }
  
  try {
    const response = await fetch(`${API_URL}/auth/register`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(formData)
    });
    
    const data = await response.json();
    
    if (data.success) {
      // Store token and user info
      localStorage.setItem('token', data.data.token);
      localStorage.setItem('user', JSON.stringify(data.data.user));
      localStorage.setItem('isLoggedIn', 'true');
      
      alert('Registration successful!');
      window.location.href = 'index.html';
    } else {
      alert(data.message || 'Registration failed');
    }
  } catch (error) {
    console.error('Registration error:', error);
    alert('An error occurred. Please try again.');
  }
});
```

---

### 3. Check Authentication Status

Add this to every protected page:

```javascript
function checkAuth() {
  const token = localStorage.getItem('token');
  const isLoggedIn = localStorage.getItem('isLoggedIn');
  
  if (!token || isLoggedIn !== 'true') {
    window.location.href = 'login.html';
    return;
  }
  
  // Optionally verify token is still valid
  verifyToken(token);
}

async function verifyToken(token) {
  try {
    const response = await fetch(`${API_URL}/auth/me`, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });
    
    if (!response.ok) {
      // Token invalid, redirect to login
      localStorage.clear();
      window.location.href = 'login.html';
    }
  } catch (error) {
    console.error('Token verification failed:', error);
  }
}

// Call on page load
checkAuth();
```

---

## 🔄 Making API Calls

### Create an API Helper Class

Add this to your frontend (create a new file `api.js`):

```javascript
class API {
  constructor(baseURL) {
    this.baseURL = baseURL;
  }
  
  getAuthHeaders() {
    const token = localStorage.getItem('token');
    return {
      'Content-Type': 'application/json',
      'Authorization': token ? `Bearer ${token}` : ''
    };
  }
  
  async request(endpoint, options = {}) {
    const url = `${this.baseURL}${endpoint}`;
    const config = {
      ...options,
      headers: {
        ...this.getAuthHeaders(),
        ...options.headers
      }
    };
    
    try {
      const response = await fetch(url, config);
      const data = await response.json();
      
      if (!response.ok) {
        if (response.status === 401) {
          // Token expired, redirect to login
          localStorage.clear();
          window.location.href = 'login.html';
        }
        throw new Error(data.message || 'Request failed');
      }
      
      return data;
    } catch (error) {
      console.error('API Error:', error);
      throw error;
    }
  }
  
  get(endpoint) {
    return this.request(endpoint, { method: 'GET' });
  }
  
  post(endpoint, body) {
    return this.request(endpoint, {
      method: 'POST',
      body: JSON.stringify(body)
    });
  }
  
  put(endpoint, body) {
    return this.request(endpoint, {
      method: 'PUT',
      body: JSON.stringify(body)
    });
  }
  
  delete(endpoint) {
    return this.request(endpoint, { method: 'DELETE' });
  }
}

// Initialize API
const api = new API('http://localhost:8000/api');
```

---

## 📝 Code Examples

### 1. Load Courses (`skill-shop.html`)

```javascript
async function loadCourses() {
  try {
    const data = await api.get('/courses?status=approved&page=1&limit=20');
    
    const coursesContainer = document.getElementById('coursesGrid');
    coursesContainer.innerHTML = '';
    
    data.data.courses.forEach(course => {
      const courseCard = createCourseCard(course);
      coursesContainer.appendChild(courseCard);
    });
  } catch (error) {
    console.error('Failed to load courses:', error);
  }
}

function createCourseCard(course) {
  const card = document.createElement('div');
  card.className = 'course-card';
  card.innerHTML = `
    <img src="${course.image_url || 'default-course.jpg'}" alt="${course.title}">
    <div class="card-content">
      <h3>${course.title}</h3>
      <p>${course.description}</p>
      <p class="teacher">By ${course.teacher}</p>
      <p class="price">$${course.price}</p>
      <button onclick="purchaseCourse('${course.id}')">Enroll Now</button>
    </div>
  `;
  return card;
}

async function purchaseCourse(courseId) {
  try {
    const data = await api.post('/courses/purchase', { course_id: courseId });
    
    if (data.success) {
      alert('Successfully enrolled in course!');
      window.location.href = 'mycourses.html';
    }
  } catch (error) {
    alert('Failed to enroll: ' + error.message);
  }
}

// Load on page load
window.addEventListener('load', loadCourses);
```

---

### 2. Load User Profile (`profile.html`)

```javascript
async function loadProfile() {
  try {
    const data = await api.get('/users/me');
    const user = data.data.user || data.data;
    
    // Update UI with user data
    document.getElementById('firstName').value = user.first_name;
    document.getElementById('lastName').value = user.last_name;
    document.getElementById('email').value = user.email;
    document.getElementById('phone').value = user.phone;
    document.getElementById('balance').textContent = `$${user.balance}`;
    
    if (user.avatar) {
      document.getElementById('avatar').src = user.avatar;
    }
  } catch (error) {
    console.error('Failed to load profile:', error);
  }
}

async function updateProfile() {
  const updatedData = {
    first_name: document.getElementById('firstName').value,
    last_name: document.getElementById('lastName').value,
    phone: document.getElementById('phone').value
  };
  
  try {
    const data = await api.put('/users/me', updatedData);
    
    if (data.success) {
      alert('Profile updated successfully!');
    }
  } catch (error) {
    alert('Failed to update profile: ' + error.message);
  }
}

window.addEventListener('load', loadProfile);
```

---

### 3. Upload Avatar

```javascript
async function uploadAvatar() {
  const fileInput = document.getElementById('avatarInput');
  const file = fileInput.files[0];
  
  if (!file) {
    alert('Please select a file');
    return;
  }
  
  const formData = new FormData();
  formData.append('avatar', file);
  
  try {
    const token = localStorage.getItem('token');
    const response = await fetch(`${API_URL}/users/me/avatar`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`
      },
      body: formData
    });
    
    const data = await response.json();
    
    if (data.success) {
      document.getElementById('avatar').src = data.data.avatar_url;
      alert('Avatar updated successfully!');
    }
  } catch (error) {
    console.error('Avatar upload failed:', error);
    alert('Failed to upload avatar');
  }
}
```

---

### 4. Load My Courses (`mycourses.html`)

```javascript
async function loadMyCourses() {
  try {
    const data = await api.get('/my-courses');
    
    const coursesContainer = document.getElementById('myCoursesGrid');
    coursesContainer.innerHTML = '';
    
    if (data.data.courses.length === 0) {
      coursesContainer.innerHTML = '<p>You haven\'t enrolled in any courses yet.</p>';
      return;
    }
    
    data.data.courses.forEach(course => {
      const courseCard = createMyCourseCard(course);
      coursesContainer.appendChild(courseCard);
    });
  } catch (error) {
    console.error('Failed to load courses:', error);
  }
}

function createMyCourseCard(course) {
  const card = document.createElement('div');
  card.className = 'course-card';
  card.innerHTML = `
    <img src="${course.image_url || 'default-course.jpg'}" alt="${course.title}">
    <div class="card-content">
      <h3>${course.title}</h3>
      <p>${course.description}</p>
      <p class="teacher">By ${course.teacher}</p>
      <button onclick="viewCourse('${course.course_id}')">Continue Learning</button>
    </div>
  `;
  return card;
}

window.addEventListener('load', loadMyCourses);
```

---

### 5. Admin Dashboard (`dashboard.html`)

```javascript
async function loadDashboardStats() {
  try {
    const data = await api.get('/admin/dashboard');
    const stats = data.data;
    
    document.getElementById('totalUsers').textContent = stats.total_users;
    document.getElementById('totalCourses').textContent = stats.total_courses;
    document.getElementById('pendingApplications').textContent = stats.pending_applications;
    
  } catch (error) {
    console.error('Failed to load dashboard:', error);
  }
}

async function loadRecentApplications() {
  try {
    const data = await api.get('/admin/applications?status=pending&limit=5');
    
    const tbody = document.getElementById('recentApplications');
    tbody.innerHTML = '';
    
    data.data.applications.forEach(app => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${app.first_name} ${app.last_name}</td>
        <td>${app.course_title}</td>
        <td>${new Date(app.applied_at).toLocaleDateString()}</td>
        <td><span class="status-pending">Pending</span></td>
      `;
      tbody.appendChild(row);
    });
  } catch (error) {
    console.error('Failed to load applications:', error);
  }
}

window.addEventListener('load', () => {
  loadDashboardStats();
  loadRecentApplications();
});
```

---

## 🚨 Error Handling

### Global Error Handler

```javascript
window.addEventListener('unhandledrejection', event => {
  console.error('Unhandled promise rejection:', event.reason);
  
  // Show user-friendly message
  if (event.reason.message.includes('401')) {
    alert('Your session has expired. Please login again.');
    localStorage.clear();
    window.location.href = 'login.html';
  } else {
    alert('An error occurred. Please try again.');
  }
});
```

---

## ✅ Testing Checklist

- [ ] Login works and stores token
- [ ] Signup creates new user
- [ ] Protected pages redirect to login if not authenticated
- [ ] Courses load on skill-shop page
- [ ] Course purchase/enrollment works
- [ ] Profile page loads user data
- [ ] Profile update saves changes
- [ ] Avatar upload works
- [ ] My courses displays enrolled courses
- [ ] Admin dashboard loads (for admin users)
- [ ] Logout clears token and redirects

---

## 🎯 Quick Test

1. Start backend: `php -S localhost:8000 -t public`
2. Open `login.html` in browser
3. Login with: `admin@skillswap.com` / `Admin@123`
4. Check browser console for API calls
5. Verify data loads correctly

---

## 📞 Need Help?

If APIs aren't working:
1. Check browser console for errors
2. Verify backend is running
3. Check CORS settings in `.env`
4. Test endpoints in Postman first

---

**Happy Coding! 🚀**
