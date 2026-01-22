(function(){
  const cfg = window.B2BCS || {};
  const clientID = cfg.clientID || '';
  const secretKey = cfg.secretKey || '';
  const apiBase = cfg.apiBase || 'https://course-dashboard.com';
  const ajaxUrl = cfg.ajaxUrl;
  const clientCourseIds = Array.isArray(cfg.courseIds) ? cfg.courseIds : [];

  const coursesContainer = document.getElementById('show-courses-container');
  const loader = document.getElementById('loader');

  function getUrlParameter(name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
  }

  function fetchPriceAndUrl(courseId) {
    const body = new URLSearchParams();
    body.set('action', 'get_course_price');
    body.set('course_id', String(courseId));
    return fetch(ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    })
      .then(r => r.json())
      .catch(() => ({ success: false }));
  }

  function displayCourses(courses) {
    if (!coursesContainer) return;
    coursesContainer.innerHTML = '';
    courses.forEach(course => {
      const courseElement = document.createElement('div');
      courseElement.classList.add('e-all-course-page-single-card');
      courseElement.id = `course-${course.id}`;
      const courseTitle = course.title || 'No title available';
      const price = course.priceData && course.priceData.success ? course.priceData.data.price : 'N/A';
      const productUrl = course.priceData && course.priceData.success ? course.priceData.data.product_url : `#`;
      courseElement.innerHTML = `
        <div class="e-all-course-single-card-img">
          <a class="course-link" href="${productUrl}"><img src="${course.thumbnail}" alt="${courseTitle}" /></a>
        </div>
        <div class="e-all-course-single-card-title">
          <a class="course-link" href="${productUrl}">${courseTitle}</a>
          <p class="course-price">${price}</p>
        </div>
      `;
      coursesContainer.appendChild(courseElement);
    });
    if (loader) loader.style.display = 'none';
    if (coursesContainer) coursesContainer.style.display = 'grid';
  }

  function setupPagination(totalPages, currentCpage, type, category) {
    const paginationContainer = document.getElementById('course-dir-pag-bottom');
    const paginationCount = document.getElementById('course-dir-count-bottom');
    if (!paginationContainer) return;

    if (paginationCount) {
      paginationCount.textContent = `Viewing page ${currentCpage} of ${totalPages}`;
    }
    paginationContainer.innerHTML = '';

    const maxPagesToShow = 3;
    let startPage = Math.max(1, currentCpage - Math.floor(maxPagesToShow / 2));
    let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);
    if (endPage - startPage + 1 < maxPagesToShow) {
      startPage = Math.max(1, endPage - maxPagesToShow + 1);
    }
    if (currentCpage > 1) {
      const prevButton = document.createElement('a');
      prevButton.classList.add('page-numbers', 'prev');
      prevButton.textContent = '←';
      prevButton.addEventListener('click', () => fetchCourses(currentCpage - 1, type, category));
      paginationContainer.appendChild(prevButton);
    }
    for (let i = startPage; i <= endPage; i++) {
      const pageLink = document.createElement('a');
      pageLink.textContent = i;
      pageLink.classList.add('page-numbers');
      if (i === currentCpage) {
        pageLink.classList.add('current');
        pageLink.setAttribute('aria-current', 'page');
      } else {
        pageLink.href = '#';
        pageLink.addEventListener('click', () => fetchCourses(i, type, category));
      }
      paginationContainer.appendChild(pageLink);
    }
    if (currentCpage < totalPages) {
      const nextButton = document.createElement('a');
      nextButton.classList.add('page-numbers', 'next');
      nextButton.textContent = '→';
      nextButton.addEventListener('click', () => fetchCourses(currentCpage + 1, type, category));
      paginationContainer.appendChild(nextButton);
    }
  }

  function fetchCourses(cpage = 1, type = 'general', category = '') {
    const perPage = 16;
    if (loader) loader.style.display = 'flex';
    if (coursesContainer) coursesContainer.style.display = 'none';
    const effectiveType = (type === 'alphabetical') ? 'general' : type;
    const newUrl = `${window.location.pathname}?cpage=${cpage}&type=${type}&category=${category}`;
    window.history.pushState({ path: newUrl }, '', newUrl);

    fetch(`${apiBase}/wp-json/custom/v1/connected-courses-batch-size/`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${clientID}:${secretKey}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ cpage, per_page: perPage, type: effectiveType, category, course_ids: clientCourseIds })
    })
      .then(response => {
        if (!response.ok) { throw new Error('Invalid API key or other error'); }
        return response.json();
      })
      .then(async data => {
        if (data.courses) {
          const coursesWithPrice = await Promise.all((data.courses || []).map(async course => {
            const priceData = await fetchPriceAndUrl(course.id);
            course.priceData = priceData;
            return course;
          }));
          let validCourses = coursesWithPrice.filter(course => course.priceData && course.priceData.success && course.priceData.data.price !== 'N/A' && course.priceData.data.price !== 'Error fetching price');
          if (type === 'alphabetical') {
            validCourses.sort((a, b) => (a.title || '').localeCompare(b.title || ''));
          }
          displayCourses(validCourses);
          const totalPages = data.total_pages || 1;
          setupPagination(totalPages, cpage, type, category);
        }
      })
      .catch(() => {
        alert('There was an issue fetching the courses. Please try again later.');
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    // Load categories
    const dropdownContent = document.getElementById('catDropdown');
    const dropBtnText = document.getElementById('drpdwntxt');
    const nxtTitle = document.querySelector('#taf-title-course h1');
    if (dropdownContent) {
      fetch(`${apiBase}/wp-json/custom/v1/course-categories/`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${clientID}:${secretKey}`,
          'Content-Type': 'application/json'
        }
      }).then(r => r.json()).then(data => {
        dropdownContent.innerHTML = '';
        dropdownContent.innerHTML += `<a href="#" data-id="" class="cat-item">All Courses</a>`;
        (data && data.success ? data.data || [] : []).forEach(category => {
          dropdownContent.innerHTML += `<a href="#" data-id="${category.slug}" class="cat-item">${category.name}</a>`;
        });
        dropdownContent.addEventListener('click', function (e) {
          if (e.target && e.target.matches('a.cat-item')) {
            const selectedCategory = e.target.textContent;
            if (nxtTitle) nxtTitle.textContent = selectedCategory;
            if (dropBtnText) dropBtnText.textContent = selectedCategory;
            const newSelectedCategory = e.target.getAttribute('data-id') || '';
            fetchCourses(1, 'general', newSelectedCategory);
          }
        });
      });
    }

    const initialCpage = parseInt(getUrlParameter('cpage') || '1', 10);
    const initialType = getUrlParameter('type') || 'general';
    const initialCategory = getUrlParameter('category') || '';
    fetchCourses(initialCpage, initialType, initialCategory);

    // Handle Enroll button clicks for subscription users
    const enrollButtons = document.querySelectorAll('.b2b-cs-enroll-btn');
    if (enrollButtons.length > 0) {
      const enrollAjaxUrl = window.b2bCsAjaxUrl || ajaxUrl || '/wp-admin/admin-ajax.php';
      
      enrollButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
          e.preventDefault();
          
          const courseId = this.getAttribute('data-course-id') || this.dataset.courseId;
          if (!courseId) {
            alert('Course ID not found.');
            return;
          }

          const originalText = this.textContent;
          this.disabled = true;
          this.textContent = 'Enrolling...';

          const formData = new URLSearchParams();
          formData.append('action', 'b2b_cs_enroll_course');
          formData.append('course_id', courseId);
          
          // Get nonce from window object or meta tag
          const nonce = window.b2bCsEnrollNonce || (function() {
            const nonceInput = document.querySelector('input[name="_ajax_nonce"]');
            return nonceInput ? nonceInput.value : '';
          })();
          if (nonce) {
            formData.append('_ajax_nonce', nonce);
          }

          fetch(enrollAjaxUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString(),
            credentials: 'same-origin'
          })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                this.textContent = 'Enrolled!';
                this.classList.remove('b2b-cs-enroll-btn');
                this.classList.add('b2b-cs-enrolled');
                this.style.opacity = '0.7';
                this.style.cursor = 'default';
                
                if (data.data && data.data.message) {
                  alert(data.data.message);
                }
                
                setTimeout(function() {
                  location.reload();
                }, 1500);
              } else {
                this.disabled = false;
                this.textContent = originalText;
                alert(data.data && data.data.message ? data.data.message : 'Failed to enroll. Please try again.');
              }
            })
            .catch(error => {
              this.disabled = false;
              this.textContent = originalText;
              alert('An error occurred. Please try again.');
              console.error('Enrollment error:', error);
            });
        });
      });
    }
  });
})();
