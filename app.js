(() => {
  const app = document.getElementById('app');
  const toast = document.getElementById('toast');
  const homeMarkup = app.innerHTML;
  const savedToken = localStorage.getItem('gozorx_token');
  const savedUser = localStorage.getItem('gozorx_user');

  const state = {
    page: 'home',
    suppliers: [],
    products: [],
    selectedProduct: null,
    cart: [],
    language: 'ar',
    token: savedToken || null,
    user: savedUser ? JSON.parse(savedUser) : null
  };

  const categories = ['أشجار الفاكهة والشتلات المطعومة','أشجار الزينة واللاندسكيب','أشجار الغابات والأخشاب','الزيتون والموالح والمحاصيل المتخصصة'];
  const API_BASE = 'https://curly-space-enigma-7v9xggrg794v3r9xj-8000.app.github.dev/api/v1';

  async function apiRequest(endpoint, options = {}) {
    const headers = {
      'Accept': 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...(state.token ? { 'Authorization': 'Bearer ' + state.token } : {}),
      ...(options.headers || {})
    };

    const response = await fetch(API_BASE + endpoint, {
      ...options,
      headers
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      const errors = data.errors
        ? Object.values(data.errors).flat().join(' ')
        : '';

      throw new Error(errors || data.message || 'حدث خطأ أثناء الاتصال بالخادم.');
    }

    return data;
  }

  function saveSession(data) {
    state.token = data.token;
    state.user = data.user;

    localStorage.setItem('gozorx_token', data.token);
    localStorage.setItem('gozorx_user', JSON.stringify(data.user));
  }

  function clearSession() {
    state.token = null;
    state.user = null;

    localStorage.removeItem('gozorx_token');
    localStorage.removeItem('gozorx_user');
  }

  function updateAccountArea() {
    const area = document.getElementById('accountArea');
    if (!area) return;

    if (state.token && state.user) {
      const name = state.user.full_name || state.user.name || 'حسابي';
      area.innerHTML =
        '<span class="account-name"></span> ' +
        (state.user.role === 'admin'
          ? '<button class="outline" data-page="admin-payments" type="button">مراجعة المدفوعات</button> '
          : '') +
        '<button class="outline" id="logoutBtn" type="button">تسجيل الخروج</button>';

      area.querySelector('.account-name').textContent = name;
    } else {
      area.innerHTML =
        '<button class="outline" data-page="login" type="button">دخول</button>';
    }
  }
  function notify(msg){toast.textContent=msg;toast.classList.add('show');setTimeout(()=>toast.classList.remove('show'),2600);}
  function updateCartCount(){
    const count = state.cart.reduce((sum, item) => sum + item.quantity, 0);
    const badge = document.getElementById('cartCount');
    if(badge) badge.textContent = count;
  }
  function go(page){state.page=page;render();window.scrollTo({top:0,behavior:'smooth'});}
  function field(label,name,type='text',required=true){return '<div class="field"><label>'+label+'</label><input name="'+name+'" type="'+type+'" '+(required?'required':'')+'></div>';}
  function shell(title,subtitle,body){return '<section class="page"><div class="page-title"><span class="eyebrow">GOZORX</span><h1>'+title+'</h1><p>'+subtitle+'</p></div>'+body+'</section>';}
  function escapeAdminHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    })[ch]);
  }

  async function loadAdminPayments() {
    const container = document.getElementById('adminPaymentsList');
    if (!container) return;

    container.innerHTML = '<div class="empty">جارٍ تحميل الإيصالات المعلقة...</div>';

    try {
      const result = await apiRequest('/admin/payments/deposit-receipts');
      const payments = result.data || [];

      if (!payments.length) {
        container.innerHTML = '<div class="empty">لا توجد إيصالات عربون معلقة للمراجعة حاليًا.</div>';
        return;
      }

      container.innerHTML = payments.map(payment => {
        const order = payment.order || {};
        const buyer = order.buyer || {};
        const buyerName = buyer.full_name || buyer.name || 'عميل';
        const amount = Number(payment.amount || order.deposit_amount || 0);
        const expiresAt = order.deposit_expires_at
          ? new Date(order.deposit_expires_at).toLocaleString('ar-EG')
          : 'غير محدد';

        return `
          <article class="panel admin-payment-card">
            <div class="admin-payment-heading">
              <h3>طلب رقم: ${escapeAdminHtml(order.order_number || order.id || '—')}</h3>
              <span class="status pending">بانتظار المراجعة</span>
            </div>

            <div class="admin-payment-grid">
              <div><strong>العميل:</strong> ${escapeAdminHtml(buyerName)}</div>
              <div><strong>الهاتف:</strong> ${escapeAdminHtml(buyer.phone || order.recipient_phone || '—')}</div>
              <div><strong>البريد:</strong> ${escapeAdminHtml(buyer.email || '—')}</div>
              <div><strong>طريقة الدفع:</strong> ${escapeAdminHtml(payment.payment_method || '—')}</div>
              <div><strong>رقم التحويل:</strong> ${escapeAdminHtml(payment.transaction_reference || '—')}</div>
              <div><strong>قيمة العربون:</strong> ${amount.toLocaleString('ar-EG')} جنيه</div>
              <div><strong>إجمالي الطلب:</strong> ${Number(order.grand_total || 0).toLocaleString('ar-EG')} جنيه</div>
              <div><strong>المتبقي عند الاستلام:</strong> ${Number(order.balance_amount || 0).toLocaleString('ar-EG')} جنيه</div>
              <div><strong>انتهاء مهلة العربون:</strong> ${escapeAdminHtml(expiresAt)}</div>
              <div><strong>رقم الإيصال:</strong> ${escapeAdminHtml(payment.id)}</div>
            </div>

            ${payment.review_note ? `<p class="hint">ملاحظة سابقة: ${escapeAdminHtml(payment.review_note)}</p>` : ''}

            <div class="admin-payment-actions">
              <button class="outline" type="button" data-admin-action="view" data-payment-id="${escapeAdminHtml(payment.id)}">عرض الإيصال</button>
              <button class="primary" type="button" data-admin-action="approve" data-payment-id="${escapeAdminHtml(payment.id)}">قبول العربون</button>
              <button class="outline danger" type="button" data-admin-action="reject" data-payment-id="${escapeAdminHtml(payment.id)}">رفض الإيصال</button>
            </div>
          </article>
        `;
      }).join('');
    } catch (error) {
      console.error('Admin payments error:', error);
      container.innerHTML =
        '<div class="empty">تعذر تحميل الإيصالات: ' +
        escapeAdminHtml(error.message) +
        ' <button class="outline" type="button" data-admin-action="refresh">إعادة المحاولة</button></div>';
    }
  }

  async function openAdminReceipt(paymentId) {
    const tab = window.open('about:blank', '_blank');

    try {
      const response = await fetch(
        API_BASE + '/admin/payments/deposit-receipts/' +
        encodeURIComponent(paymentId) + '/receipt',
        {
          headers: {
            'Accept': '*/*',
            'Authorization': 'Bearer ' + state.token
          }
        }
      );

      if (!response.ok) {
        throw new Error(response.status === 403
          ? 'ليس لديك صلاحية لعرض الإيصال.'
          : 'تعذر فتح الإيصال. HTTP ' + response.status);
      }

      const blob = await response.blob();
      const fileUrl = URL.createObjectURL(blob);

      if (tab) {
        tab.location.href = fileUrl;
      } else {
        window.open(fileUrl, '_blank');
      }

      setTimeout(() => URL.revokeObjectURL(fileUrl), 60000);
    } catch (error) {
      if (tab) tab.close();
      notify(error.message);
    }
  }

  async function handleAdminPaymentAction(action, paymentId, button) {
    if (!state.token || !state.user || state.user.role !== 'admin') {
      notify('هذه الصفحة متاحة للإدارة فقط.');
      return;
    }

    if (action === 'view') {
      await openAdminReceipt(paymentId);
      return;
    }

    if (action === 'refresh') {
      await loadAdminPayments();
      return;
    }

    let payload;

    if (action === 'approve') {
      if (!window.confirm('هل تؤكد قبول إيصال العربون وتأكيد الطلب؟')) return;
      payload = { decision: 'approved' };
    } else if (action === 'reject') {
      const reason = window.prompt('اكتب سبب رفض الإيصال:');
      if (reason === null) return;

      if (!reason.trim()) {
        notify('يجب كتابة سبب الرفض.');
        return;
      }

      payload = {
        decision: 'rejected',
        review_note: reason.trim()
      };
    } else {
      return;
    }

    if (button) {
      button.disabled = true;
      button.textContent = 'جارٍ التنفيذ...';
    }

    try {
      await apiRequest(
        '/admin/payments/deposit-receipts/' +
        encodeURIComponent(paymentId) + '/decision',
        {
          method: 'PATCH',
          body: JSON.stringify(payload)
        }
      );

      notify(action === 'approve'
        ? 'تم قبول العربون وتأكيد الطلب.'
        : 'تم رفض الإيصال وحفظ سبب الرفض.');

      await loadAdminPayments();
    } catch (error) {
      notify(error.message);
      if (button) button.disabled = false;
    }
  }

  function renderAdminPayments() {
    if (!state.token || !state.user || state.user.role !== 'admin') {
      app.innerHTML = shell(
        'غير مصرح بالدخول',
        'هذه الصفحة متاحة لحسابات الإدارة فقط.',
        '<div class="panel"><p>سجّل الدخول بحساب إداري للوصول إلى مراجعة المدفوعات.</p><button class="primary" data-page="login">تسجيل الدخول</button></div>'
      );
      return;
    }

    app.innerHTML = shell(
      'مراجعة المدفوعات',
      'راجع إيصالات تحويل العربون قبل تأكيد الطلبات.',
      '<div class="admin-payments-toolbar"><p>الإيصالات المعروضة هي الإيصالات التي تنتظر مراجعة الإدارة.</p><button class="outline" type="button" data-admin-action="refresh">تحديث القائمة</button></div><div id="adminPaymentsList"><div class="empty">جارٍ تحميل الإيصالات...</div></div>'
    );

    loadAdminPayments();
  }

  function renderHome(){return homeMarkup;}
  function render(){
    if(state.page==='home'){app.innerHTML=renderHome();return;}

    if(state.page==='admin-payments'){
      renderAdminPayments();
      return;
    }

    if(state.page==='market'){
      app.innerHTML=shell('السوق الزراعي','تصفح المنتجات المنشورة من الموردين المعتمدين على GOZORX.','<div class="toolbar"><input id="searchProduct" placeholder="ابحث عن منتج..."><select id="categoryFilter"><option value="">كل الأقسام</option></select></div><div class="product-grid" id="products"><div class="empty">جارٍ تحميل المنتجات...</div></div>');

      const container = document.getElementById('products');
      const searchInput = document.getElementById('searchProduct');
      const categoryFilter = document.getElementById('categoryFilter');

      function safe(value){
        return String(value ?? '').replace(/[&<>"']/g, ch => ({
          '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'
        })[ch]);
      }

      function flattenCategories(items, level = 0){
        let result = [];
        items.forEach(category => {
          result.push({ ...category, level });
          if(category.children?.length){
            result = result.concat(flattenCategories(category.children, level + 1));
          }
        });
        return result;
      }

      function draw(products){
        container.innerHTML = products.map(p => `
          <article class="product" data-product-id="${safe(p.id)}" role="button" tabindex="0">
            <div class="product-img">🌱</div>
            <div class="product-body">
              <h3>${safe(p.name_ar || p.name_en || 'منتج زراعي')}</h3>
              <p>${safe(p.category?.name_ar || 'قسم غير محدد')}</p>
              <div class="price">${Number(p.unit_price || 0).toLocaleString('ar-EG')} جنيه / ${safe(p.unit_name || 'وحدة')}</div>
              <div class="product-meta">
                <span>الحد الأدنى: ${safe(p.minimum_order_quantity ?? 1)}</span>
                <span>المتاح: ${safe(p.available_quantity ?? 'غير محدد')}</span>
              </div>
              <div class="product-meta"><span>المورد: ${safe(p.supplier?.business_name || 'مورد معتمد')}</span></div>
            </div>
          </article>
        `).join('') || '<div class="empty">لا توجد منتجات مطابقة للبحث.</div>';
      }

      async function loadProducts(){
        container.innerHTML = '<div class="empty">جارٍ تحميل المنتجات...</div>';

        const params = new URLSearchParams();
        if(searchInput.value.trim()) params.set('search', searchInput.value.trim());
        if(categoryFilter.value) params.set('category_id', categoryFilter.value);

        try {
          const response = await fetch(API_BASE + '/products?' + params.toString());
          if(!response.ok) throw new Error('HTTP ' + response.status);
          const result = await response.json();
          state.products = result.data || [];
          draw(state.products);
        } catch(error) {
          console.error('GOZORX products API error:', error);
          container.innerHTML = '<div class="empty">تعذر تحميل المنتجات. حاول مرة أخرى.</div>';
        }
      }

      async function loadCategories(){
        try {
          const response = await fetch(API_BASE + '/categories');
          if(!response.ok) throw new Error('HTTP ' + response.status);
          const result = await response.json();
          const allCategories = flattenCategories(result.data || []);

          categoryFilter.innerHTML = '<option value="">كل الأقسام</option>' +
            allCategories.map(category =>
              '<option value="' + category.id + '">' +
              (category.level ? '— '.repeat(category.level) : '') +
              safe(category.name_ar) + '</option>'
            ).join('');
        } catch(error) {
          console.error('GOZORX categories API error:', error);
        }
      }

      let searchTimer;
      searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadProducts, 350);
      });

      categoryFilter.addEventListener('change', loadProducts);

      Promise.all([loadCategories(), loadProducts()]);
      return;
    }
    if(state.page==='cart'){
      const total = state.cart.reduce((sum, item) => sum + Number(item.product.unit_price || 0) * item.quantity, 0);

      const cartBody = state.cart.length ? `
        <div class="cart-layout">
          <div class="cart-items">
            ${state.cart.map(item => `
              <article class="cart-item">
                <div class="cart-item-icon">🌱</div>
                <div class="cart-item-info">
                  <h3>${String(item.product.name_ar || item.product.name_en || 'منتج زراعي').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))}</h3>
                  <p>المورد: ${String(item.product.supplier?.business_name || 'مورد معتمد').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))}</p>
                  <p>سعر الوحدة: ${Number(item.product.unit_price || 0).toLocaleString('ar-EG')} جنيه</p>
                  <label>الكمية:
                    <input class="cart-quantity" type="number" min="${Number(item.product.minimum_order_quantity || 1)}" max="${Number(item.product.available_quantity || 999999)}" value="${item.quantity}" data-cart-qty="${item.product.id}">
                  </label>
                  <button class="textbtn cart-remove" data-cart-remove="${item.product.id}">حذف المنتج</button>
                </div>
                <strong class="cart-line-total">${(Number(item.product.unit_price || 0) * item.quantity).toLocaleString('ar-EG')} جنيه</strong>
              </article>
            `).join('')}
          </div>
          <aside class="cart-summary">
            <h3>ملخص الطلب</h3>
            <p>عدد الوحدات: <strong>${state.cart.reduce((sum, item) => sum + item.quantity, 0)}</strong></p>
            <div class="cart-total">${total.toLocaleString('ar-EG')} جنيه</div>
            <button class="primary" id="checkoutBtn" type="button">متابعة إتمام الطلب</button>
            <small class="hint">إتمام الطلب والدفع الإلكتروني لم يتم تفعيلهما بعد.</small>
          </aside>
        </div>
      ` : '<div class="panel empty">سلة المشتريات فارغة حاليًا. تصفح السوق وأضف المنتجات التي تحتاجها.</div><button class="primary" data-page="market">تصفح السوق</button>';

      app.innerHTML = shell('سلة المشتريات','راجع المنتجات والكميات قبل إتمام الطلب.',cartBody);
      return;
    }

    if(state.page==='product'){
      const p = state.selectedProduct;
      if(!p){
        app.innerHTML = shell('تفاصيل المنتج','لم يتم اختيار منتج.','<button class="primary" data-page="market">العودة إلى السوق</button>');
        return;
      }

      const safeDetail = value => String(value ?? '').replace(/[&<>"']/g, ch => ({
        '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'
      })[ch]);

      app.innerHTML = shell(
        'تفاصيل المنتج',
        'تعرف على مواصفات المنتج ومعلومات المورد.',
        `<button class="textbtn detail-back" data-page="market">← العودة إلى السوق</button>
        <div class="product-detail">
          <div class="product-detail-image">🌱</div>
          <div class="product-detail-body">
            <span class="eyebrow">GOZORX MARKET</span>
            <h2>${safeDetail(p.name_ar || p.name_en || 'منتج زراعي')}</h2>
            <p class="detail-category">${safeDetail(p.category?.name_ar || 'قسم غير محدد')}</p>
            <div class="detail-price">${Number(p.unit_price || 0).toLocaleString('ar-EG')} جنيه / ${safeDetail(p.unit_name || 'وحدة')}</div>
            <div class="detail-info">
              <div><span>الحد الأدنى للطلب</span><strong>${safeDetail(p.minimum_order_quantity ?? 1)}</strong></div>
              <div><span>الكمية المتاحة</span><strong>${safeDetail(p.available_quantity ?? 'غير محدد')}</strong></div>
              <div><span>المورد</span><strong>${safeDetail(p.supplier?.business_name || 'مورد معتمد')}</strong></div>
            </div>
            <div class="detail-description">
              <h3>وصف المنتج</h3>
              <p>${safeDetail(p.description_ar || p.description || 'لا يوجد وصف إضافي لهذا المنتج حاليًا.')}</p>
            </div>
            <label class="detail-quantity-label">الكمية المطلوبة
              <input id="detailQuantity" type="number" min="${Number(p.minimum_order_quantity || 1)}" max="${Number(p.available_quantity || 999999)}" value="${Number(p.minimum_order_quantity || 1)}">
            </label>
            <button class="primary" type="button" id="detailCartBtn">أضف إلى السلة</button>
          </div>
        </div>`
      );

      document.getElementById('detailCartBtn').addEventListener('click', () => {
        const quantityInput = document.getElementById('detailQuantity');
        const quantity = Number(quantityInput.value);
        const minimum = Number(p.minimum_order_quantity || 1);
        const available = Number(p.available_quantity ?? 999999);

        if(!Number.isInteger(quantity) || quantity < minimum){
          notify('الكمية أقل من الحد الأدنى للطلب أو غير صحيحة');
          quantityInput.value = minimum;
          return;
        }
        if(quantity > available){
          notify('الكمية المطلوبة أكبر من الكمية المتاحة');
          quantityInput.value = available;
          return;
        }

        const existing = state.cart.find(item => String(item.product.id) === String(p.id));
        if(existing){
          if(existing.quantity + quantity > available){
            notify('إجمالي الكمية في السلة أكبر من المتاح');
            return;
          }
          existing.quantity += quantity;
        } else {
          state.cart.push({ product: p, quantity });
        }

        updateCartCount();
        notify('تمت إضافة المنتج إلى السلة');
      });
      return;
    }

    if(state.page==='join'){
      app.innerHTML=shell('طلب انضمام مورد','قدّم بياناتك للمراجعة. لن تتمكن من نشر المنتجات قبل موافقة الإدارة.','<form class="panel form-grid" id="supplierForm">'+field('اسم المسؤول','contact_name')+field('اسم المشتل / الشركة','business_name')+field('رقم الهاتف','phone','tel')+field('البريد الإلكتروني','email','email')+'<div class="field"><label>المحافظة</label><input name="governorate" required></div><div class="field"><label>المدينة</label><input name="city" required></div><div class="field full"><label>نبذة عن النشاط والمنتجات</label><textarea name="description" required></textarea></div><div class="field full"><button class="primary">إرسال طلب الانضمام</button><small class="hint">هذه نسخة عرض: الطلب لا يُحفظ على خادم ولا يُرسل للإدارة فعليًا.</small></div></form>');
      document.getElementById('supplierForm').addEventListener('submit',e=>{e.preventDefault();state.suppliers.push(Object.fromEntries(new FormData(e.target)));notify('تم تسجيل الطلب في هذه الجلسة التجريبية فقط');e.target.reset();});return;
    }
    if(state.page==='login'){
      app.innerHTML=shell(
        'تسجيل الدخول',
        'سجّل الدخول إلى حسابك لمتابعة مشترياتك وطلباتك.',
        '<form class="panel form-grid" id="loginForm">'+
        field('البريد الإلكتروني','email','email')+
        field('كلمة المرور','password','password')+
        '<div class="field full"><button class="primary" type="submit">تسجيل الدخول</button></div>'+
        '</form><p><button class="textbtn" data-page="register">إنشاء حساب مشتري جديد</button></p>'
      );

      document.getElementById('loginForm').addEventListener('submit', async e => {
        e.preventDefault();

        const form = e.target;
        const button = form.querySelector('button[type="submit"]');
        const formData = Object.fromEntries(new FormData(form));

        button.disabled = true;
        button.textContent = 'جارٍ تسجيل الدخول...';

        try {
          const data = await apiRequest('/login', {
            method: 'POST',
            body: JSON.stringify(formData)
          });

          saveSession(data);
          updateAccountArea();
          notify('تم تسجيل الدخول بنجاح');
          go('home');
        } catch (error) {
          notify(error.message);
        } finally {
          button.disabled = false;
          button.textContent = 'تسجيل الدخول';
        }
      });

      return;
    }

    if(state.page==='register'){
      app.innerHTML=shell(
        'إنشاء حساب مشتري',
        'أنشئ حسابك للشراء وإدارة طلباتك على GOZORX.',
        '<form class="panel form-grid" id="registerForm">'+
        field('الاسم بالكامل','full_name')+
        field('رقم الهاتف','phone','tel')+
        field('البريد الإلكتروني','email','email')+
        field('كلمة المرور (10 أحرف على الأقل)','password','password')+
        field('تأكيد كلمة المرور','password_confirmation','password')+
        '<div class="field"><label>المحافظة (اختياري)</label><input name="governorate"></div>'+
        '<div class="field"><label>المدينة (اختياري)</label><input name="city"></div>'+
        '<div class="field full"><button class="primary" type="submit">إنشاء الحساب</button></div>'+
        '</form><p><button class="textbtn" data-page="login">لديك حساب بالفعل؟ تسجيل الدخول</button></p>'
      );

      document.getElementById('registerForm').addEventListener('submit', async e => {
        e.preventDefault();

        const form = e.target;
        const button = form.querySelector('button[type="submit"]');
        const formData = Object.fromEntries(new FormData(form));

        if(formData.password.length < 10){
          notify('كلمة المرور يجب ألا تقل عن 10 أحرف');
          return;
        }

        if(formData.password !== formData.password_confirmation){
          notify('كلمتا المرور غير متطابقتين');
          return;
        }

        button.disabled = true;
        button.textContent = 'جارٍ إنشاء الحساب...';

        try {
          const data = await apiRequest('/register', {
            method: 'POST',
            body: JSON.stringify(formData)
          });

          saveSession(data);
          updateAccountArea();
          notify('تم إنشاء حسابك بنجاح');
          go('home');
        } catch (error) {
          notify(error.message);
        } finally {
          button.disabled = false;
          button.textContent = 'إنشاء الحساب';
        }
      });

      return;
    }

    if(state.page==='rfq'||state.page==='nursery'){
      const nursery=state.page==='nursery';
      app.innerHTML=shell(nursery?'طلبات المشاتل':'طلب عرض سعر لمشروع','أرسل احتياجاتك وسيتواصل معك فريق GOZORX بعد تفعيل النظام.','<form class="panel form-grid" id="requestForm">'+field('الاسم','name')+field('رقم الهاتف','phone','tel')+field('البريد الإلكتروني','email','email')+field('المحافظة','governorate')+'<div class="field full"><label>الأصناف والكميات المطلوبة</label><textarea name="details" required></textarea></div><div class="field full"><button class="primary">إرسال الطلب</button><small class="hint">نموذج تجريبي ولا يرسل بيانات إلى خادم.</small></div></form>');
      document.getElementById('requestForm').addEventListener('submit',e=>{e.preventDefault();notify('النموذج تجريبي — لم يتم إرسال الطلب فعليًا');e.target.reset();});return;
    }
    app.innerHTML=shell('قريبًا','هذه الوظيفة ضمن مراحل تطوير GOZORX.','<div class="panel">سيتم تفعيلها بعد تجهيز الخادم وقاعدة البيانات.</div>');
  }
  document.addEventListener('click', async e=>{
    const adminButton = e.target.closest('[data-admin-action]');
    if (adminButton) {
      await handleAdminPaymentAction(
        adminButton.dataset.adminAction,
        adminButton.dataset.paymentId,
        adminButton
      );
      return;
    }

    if(e.target.closest('#logoutBtn')){
      try {
        if(state.token){
          await apiRequest('/logout', { method: 'POST' });
        }
      } catch(error) {
        console.warn('Logout request failed:', error.message);
      }

      clearSession();
      updateAccountArea();
      notify('تم تسجيل الخروج بنجاح');
      go('home');
      return;
    }

    if(e.target.closest('#cartBtn')){
      go('cart');
      return;
    }

    const removeButton = e.target.closest('[data-cart-remove]');
    if(removeButton){
      state.cart = state.cart.filter(item => String(item.product.id) !== removeButton.dataset.cartRemove);
      updateCartCount();
      go('cart');
      return;
    }

    if(e.target.closest('#checkoutBtn')){
      notify('إتمام الطلب والدفع قيد التطوير');
      return;
    }

    const productCard = e.target.closest('[data-product-id]');
    if(productCard){
      const product = state.products.find(p => String(p.id) === productCard.dataset.productId);
      if(product){
        state.selectedProduct = product;
        go('product');
      }
      return;
    }

    const target=e.target.closest('[data-page]');
    if(target){e.preventDefault();go(target.dataset.page);}
  });
  document.addEventListener('change', e => {
    const quantityInput = e.target.closest('[data-cart-qty]');
    if(!quantityInput) return;

    const item = state.cart.find(entry => String(entry.product.id) === quantityInput.dataset.cartQty);
    if(!item) return;

    const quantity = Number(quantityInput.value);
    const minimum = Number(item.product.minimum_order_quantity || 1);
    const available = Number(item.product.available_quantity ?? 999999);

    if(!Number.isInteger(quantity) || quantity < minimum || quantity > available){
      notify('الكمية يجب أن تكون بين الحد الأدنى والكمية المتاحة');
      quantityInput.value = item.quantity;
      return;
    }

    item.quantity = quantity;
    go('cart');
    updateCartCount();
  });

  const lang=document.getElementById('langBtn');if(lang)lang.addEventListener('click',()=>notify('الواجهة الإنجليزية قيد التطوير'));
  updateCartCount();
  updateAccountArea();
  go('home');
})();