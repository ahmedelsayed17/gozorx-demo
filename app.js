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
    currentOrder: null,
    currentAdminOrder: null,
    currentAdminSupplier: null,
    supplierDashboard: null,
    supplierOrders: [],
    currentSupplierOrder: null,
    currentSupplierProductId: null,
    returnToCheckout: false,
    language: 'ar',
    token: savedToken || null,
    user: savedUser ? JSON.parse(savedUser) : null
  };

  const categories = ['أشجار الفاكهة والشتلات المطعومة','أشجار الزينة واللاندسكيب','أشجار الغابات والأخشاب','الزيتون والموالح والمحاصيل المتخصصة'];
  const API_BASE = 'https://curly-space-enigma-7v9xggrg794v3r9xj-8000.app.github.dev/api/v1';

  async function apiRequest(endpoint, options = {}) {
    const isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData;

    const headers = {
      'Accept': 'application/json',
      ...(options.body && !isFormData ? { 'Content-Type': 'application/json' } : {}),
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
    state.supplierDashboard = null;
    refreshSupplierDashboard();
  }

  async function restoreCurrentOrder(navigate = false) {
    const orderId = localStorage.getItem('gozorx_current_order_id');
    if (!state.token || !orderId) return false;

    try {
      const result = await apiRequest(
        `/orders/${encodeURIComponent(orderId)}`
      );
      if (!result.order) throw new Error('لم يتم العثور على الطلب.');

      state.currentOrder = result.order;
      if (navigate) go('order-payment');
      return true;
    } catch (error) {
      console.warn('Order restore failed:', error.message);
      return false;
    }
  }

  async function refreshSupplierDashboard() {
    state.supplierDashboard = null;

    if (!state.token || !state.user || state.user.role !== 'supplier') {
      updateAccountArea();
      return;
    }

    try {
      const result = await apiRequest('/supplier/dashboard');
      state.supplierDashboard = result;
    } catch (error) {
      state.supplierDashboard = null;
    }

    updateAccountArea();
  }

  function clearSession() {
    state.token = null;
    state.user = null;
    state.supplierDashboard = null;

    localStorage.removeItem('gozorx_token');
    localStorage.removeItem('gozorx_user');
  }

  function updateAccountArea() {
    const area = document.getElementById('accountArea');
    const nav = document.querySelector('.topbar nav');
    const cartButton = document.getElementById('cartBtn');
    const joinButton = document.querySelector('.topbar .actions > [data-page="join"]');

    const isApprovedSupplier = Boolean(
      state.token && state.user &&
      state.user.role === 'supplier' &&
      state.supplierDashboard
    );

    document.querySelector('.topbar')?.classList.toggle('supplier-header', isApprovedSupplier);

    if (nav) {
      nav.innerHTML = isApprovedSupplier
        ? '<a href="#" data-page="market">السوق</a><a href="#" data-page="rfq">عروض الأسعار</a><a href="#" data-page="supplier-dashboard">لوحة الإدارة</a>'
        : '<a href="#" data-page="market">السوق</a><a href="#" data-page="rfq">طلبات عروض الأسعار</a><a href="#" data-page="nursery">طلبات المشاتل</a>';
    }

    if (cartButton) cartButton.hidden = isApprovedSupplier;
    if (joinButton) joinButton.hidden = isApprovedSupplier;

    if (!area) return;

    if (state.token && state.user) {
      const name = state.user.full_name || state.user.name || 'حسابي';

      area.innerHTML =
        '<span class="account-name"></span> ' +
        (isApprovedSupplier
          ? ''
          : (state.user.role === 'admin'
              ? '<button class="outline" data-page="admin-orders" type="button">إدارة الطلبات</button> <button class="outline" data-page="admin-payments" type="button">مراجعة المدفوعات</button> <button class="outline" data-page="admin-suppliers" type="button">إدارة الموردين</button> <button class="outline" data-page="admin-products" type="button">مراجعة المنتجات</button> '
              : (state.user.role === 'supplier'
                  ? (state.supplierDashboard
                      ? '<button class="outline" data-page="supplier-dashboard" type="button">لوحة المورد</button> '
                      : '') +
                    '<button class="outline" data-page="supplier-application" type="button">متابعة طلب المورد</button> '
                  : state.user.role === 'buyer'
                    ? '<button class="outline" data-page="my-orders" type="button">طلباتي</button> '
                    : ''))) +
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



  const adminOrderStatusLabels = {
    pending_payment: 'بانتظار العربون',
    pending_shipping_cost: 'بانتظار تحديد تكلفة الشحن',
    confirmed: 'تم تأكيد الطلب',
    processing: 'قيد التجهيز',
    shipped: 'تم الشحن',
    delivered: 'تم التسليم',
    cancelled: 'ملغي',
    completed: 'مكتمل'
  };

  async function loadAdminOrders() {
    const container = document.getElementById('adminOrdersList');
    if (!container) return;

    const search = document.getElementById('adminOrdersSearch')?.value.trim() || '';
    const status = document.getElementById('adminOrdersStatus')?.value || '';

    container.innerHTML = '<div class="empty">جارٍ تحميل الطلبات...</div>';

    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (status) params.set('status', status);

    try {
      const result = await apiRequest('/admin/orders' + (params.toString() ? '?' + params.toString() : ''));
      const orders = Array.isArray(result.data) ? result.data : [];

      if (!orders.length) {
        container.innerHTML = '<div class="panel empty">لا توجد طلبات تطابق البحث أو الفلتر المحدد.</div>';
        return;
      }

      container.innerHTML = orders.map(order => {
        const buyer = order.buyer || {};
        const buyerName = buyer.full_name || buyer.name || 'عميل';
        const statusLabel = adminOrderStatusLabels[order.status] || order.status || 'غير محدد';
        const createdAt = order.created_at
          ? new Date(order.created_at).toLocaleString('ar-EG')
          : '—';

        return `
          <article class="panel admin-order-card">
            <div class="admin-order-heading">
              <div>
                <span class="hint">رقم الطلب</span>
                <h3>${escapeAdminHtml(order.order_number || order.id)}</h3>
              </div>
              <span class="status">${escapeAdminHtml(statusLabel)}</span>
            </div>
            <div class="admin-order-grid">
              <div><span>العميل</span><strong>${escapeAdminHtml(buyerName)}</strong></div>
              <div><span>تاريخ الطلب</span><strong>${escapeAdminHtml(createdAt)}</strong></div>
              <div><span>إجمالي الطلب</span><strong>${Number(order.grand_total || 0).toLocaleString('ar-EG')} جنيه</strong></div>
              <div><span>العربون المطلوب</span><strong>${Number(order.deposit_amount || 0).toLocaleString('ar-EG')} جنيه</strong></div>
              <div><span>المتبقي عند الاستلام</span><strong>${Number(order.balance_amount || 0).toLocaleString('ar-EG')} جنيه</strong></div>
            </div>
            <button class="outline" type="button" data-admin-order-id="${escapeAdminHtml(order.id)}">عرض التفاصيل</button>
          </article>
        `;
      }).join('');
    } catch (error) {
      console.error('Admin orders error:', error);
      container.innerHTML =
        '<div class="panel empty">تعذر تحميل الطلبات: ' +
        escapeAdminHtml(error.message) +
        '<br><button class="outline" type="button" data-admin-orders-action="refresh">إعادة المحاولة</button></div>';
    }
  }

  function renderAdminOrders() {
    if (!state.token || !state.user || state.user.role !== 'admin') {
      app.innerHTML = shell(
        'غير مصرح بالدخول',
        'هذه الصفحة متاحة لحسابات الإدارة فقط.',
        '<div class="panel"><p>سجّل الدخول بحساب إداري للوصول إلى إدارة الطلبات.</p><button class="primary" data-page="login">تسجيل الدخول</button></div>'
      );
      return;
    }

    app.innerHTML = shell(
      'إدارة الطلبات',
      'استعرض جميع طلبات العملاء وابحث عنها وتابع تفاصيلها.',
      `<div class="admin-orders-toolbar panel">
        <div class="admin-orders-filters">
          <input id="adminOrdersSearch" type="search" placeholder="ابحث برقم الطلب أو اسم العميل أو الهاتف أو البريد">
          <select id="adminOrdersStatus">
            <option value="">كل الحالات</option>
            <option value="pending_shipping_cost">بانتظار تحديد تكلفة الشحن</option>
            <option value="pending_payment">بانتظار العربون</option>
            <option value="confirmed">تم تأكيد الطلب</option>
            <option value="processing">قيد التجهيز</option>
            <option value="shipped">تم الشحن</option>
            <option value="delivered">تم التسليم</option>
            <option value="cancelled">ملغي</option>
            <option value="completed">مكتمل</option>
          </select>
          <button class="primary" type="button" data-admin-orders-action="search">بحث وتصفية</button>
          <button class="outline" type="button" data-admin-orders-action="refresh">تحديث</button>
        </div>
      </div>
      <div id="adminOrdersList"><div class="empty">جارٍ تحميل الطلبات...</div></div>`
    );

    loadAdminOrders();
  }

  async function openAdminOrder(orderId, button) {
    if (!state.token || !state.user || state.user.role !== 'admin') {
      notify('هذه الصفحة متاحة للإدارة فقط.');
      return;
    }

    if (button) {
      button.disabled = true;
      button.textContent = 'جارٍ تحميل التفاصيل...';
    }

    try {
      const result = await apiRequest('/admin/orders/' + encodeURIComponent(orderId));
      if (!result.order) throw new Error('لم يتم العثور على الطلب.');

      state.currentAdminOrder = result.order;
      state.page = 'admin-order-details';
      render();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (error) {
      notify(error.message);
      if (button) {
        button.disabled = false;
        button.textContent = 'عرض التفاصيل';
      }
    }
  }

 function renderAdminOrderDetails() {
  if (!state.token || !state.user || state.user.role !== 'admin') {
    app.innerHTML = shell(
      'غير مصرح بالدخول',
      'هذه الصفحة متاحة لحسابات الإدارة فقط.',
      '<div class="panel"><button class="primary" data-page="login">تسجيل الدخول</button></div>'
    );
    return;
  }

  const order = state.currentAdminOrder;

  if (!order) {
    app.innerHTML = shell(
      'تفاصيل الطلب',
      'لم يتم تحميل بيانات الطلب.',
      '<div class="panel"><button class="outline" data-page="admin-orders">العودة إلى إدارة الطلبات</button></div>'
    );
    return;
  }

  const buyer = order.buyer || {};
  const payment = order.deposit_transaction || null;
  const statusLabel = adminOrderStatusLabels[order.status] || order.status || 'غير محدد';

  const nextOrderStatus = {
    confirmed: { status: 'processing', label: 'بدء التجهيز' },
    processing: { status: 'shipped', label: 'خرج للتوصيل' },
    shipped: { status: 'delivered', label: 'تأكيد التسليم' }
  }[order.status] || null;

  const orderStatusAction = nextOrderStatus
    ? `
      <section class="panel admin-order-detail">
        <h3>إدارة حالة الطلب</h3>
        <p>الحالة الحالية: <strong>${escapeAdminHtml(statusLabel)}</strong></p>
        <button type="button" class="primary" data-admin-order-status="${nextOrderStatus.status}">
          ${nextOrderStatus.label}
        </button>
      </section>
    `
    : '';

  const balanceTransaction = order.balance_transaction || null;

  const balancePaymentMethodLabels = {
    vodafone_cash: 'فودافون كاش',
    instapay: 'إنستاباي',
    cash: 'نقدًا'
  };

  const balanceSection = balanceTransaction
    ? `
      <section class="panel admin-order-detail">
        <h3>تحصيل المبلغ المتبقي</h3>
        <p><strong>المبلغ المحصل:</strong> ${Number(balanceTransaction.amount || 0).toLocaleString('ar-EG')} جنيه</p>
        <p><strong>وسيلة التحصيل:</strong> ${escapeAdminHtml(balancePaymentMethodLabels[balanceTransaction.payment_method] || balanceTransaction.payment_method || '—')}</p>
        <p><strong>تاريخ التحصيل:</strong> ${escapeAdminHtml(balanceTransaction.collected_at ? new Date(balanceTransaction.collected_at).toLocaleString('ar-EG') : '—')}</p>
        <p><strong>المسؤول عن التحصيل:</strong> ${escapeAdminHtml(balanceTransaction.collected_by?.name || '—')}</p>
        <p><strong>حالة العملية:</strong> ${escapeAdminHtml(balanceTransaction.status || '—')}</p>
      </section>
    `
    : order.status === 'delivered'
      ? `
        <section class="panel admin-order-detail">
          <h3>تحصيل المبلغ المتبقي</h3>
          <p><strong>المبلغ المطلوب تحصيله:</strong> ${Number(order.balance_amount || 0).toLocaleString('ar-EG')} جنيه</p>
          <form id="adminCollectBalanceForm">
            <label for="adminBalanceAmount">المبلغ المحصل بالجنيه</label>
            <input
              type="number"
              id="adminBalanceAmount"
              name="amount"
              min="0.01"
              max="999999999"
              step="0.01"
              value="${Number(order.balance_amount || 0).toFixed(2)}"
              required
            >

            <label for="adminBalancePaymentMethod">وسيلة التحصيل</label>
            <select id="adminBalancePaymentMethod" name="payment_method" required>
              <option value="vodafone_cash">فودافون كاش</option>
              <option value="instapay">إنستاباي</option>
              <option value="cash">نقدًا</option>
            </select>

            <button type="submit" class="primary">تسجيل التحصيل وإتمام الطلب</button>
          </form>
        </section>
      `
      : '';

  const individualItem = order.individual_item || order.individualItem || null;
  const individualSupplier = order.supplier || {};

const individualSection = individualItem
  ? `
    <section class="panel admin-order-supplier">
      <h3>تفاصيل المنتج</h3>

      <p>
        <strong>المورد:</strong>
        ${escapeAdminHtml(individualSupplier.business_name || '—')}
      </p>

      <p>
        <strong>اسم المنتج:</strong>
        ${escapeAdminHtml(
          individualItem.product_name_ar ||
          individualItem.product_name_en ||
          'منتج'
        )}
      </p>

      <p>
        <strong>الكمية:</strong>
        ${Number(individualItem.quantity || 0)}
        ${escapeAdminHtml(individualItem.unit_name || 'وحدة')}
      </p>

      <p>
        <strong>سعر الوحدة:</strong>
        ${Number(individualItem.unit_price || 0).toLocaleString('ar-EG')} جنيه
      </p>

      <p>
        <strong>إجمالي المنتج:</strong>
        ${Number(individualItem.line_total || 0).toLocaleString('ar-EG')} جنيه
      </p>
    </section>
  `
  : '';

const supplierOrders = Array.isArray(order.supplier_orders)
  ? order.supplier_orders
  : (Array.isArray(order.supplierOrders) ? order.supplierOrders : []);

const legacySupplierSections = supplierOrders.length
  ? supplierOrders.map((supplierOrder, index) => {
      const supplier = supplierOrder.supplier || {};
      const supplierUser = supplier.user || {};
      const items = Array.isArray(supplierOrder.items) ? supplierOrder.items : [];

      return `
        <section class="panel admin-order-supplier">
          <h3>طلب المورد ${index + 1}</h3>

          <p>
            <strong>المورد:</strong>
            ${escapeAdminHtml(
              supplier.business_name ||
              supplierUser.full_name ||
              supplierUser.name ||
              '—'
            )}
          </p>

          <p>
            <strong>حالة طلب المورد:</strong>
            ${escapeAdminHtml(
              adminOrderStatusLabels[supplierOrder.status] ||
              supplierOrder.status ||
              '—'
            )}
          </p>

          <p>
            <strong>الإجمالي:</strong>
            ${Number(
              supplierOrder.grand_total || supplierOrder.subtotal || 0
            ).toLocaleString('ar-EG')} جنيه
          </p>

          ${
            items.length
              ? `<ul>${items.map(item => `
                  <li>
                    ${escapeAdminHtml(
                      item.product_name_ar ||
                      item.product_name_en ||
                      item.product_name ||
                      'منتج'
                    )}
                    — الكمية: ${Number(item.quantity || 0)}
                    — ${Number(item.line_total || 0).toLocaleString('ar-EG')} جنيه
                  </li>
                `).join('')}</ul>`
              : '<p>لا توجد تفاصيل أصناف متاحة.</p>'
          }
        </section>
      `;
    }).join('')
  : '';

const supplierSections = individualSection || legacySupplierSections ||
  '<div class="panel">لا توجد تفاصيل طلبات موردين متاحة في البيانات.</div>';
const shippingHistories = Array.isArray(order.shipping_histories)
  ? order.shipping_histories
  : (Array.isArray(order.shippingHistories) ? order.shippingHistories : []);

const shippingHistorySection = shippingHistories.length
  ? `
    <section class="panel admin-order-detail">
      <h3>سجل تحديد تكلفة الشحن</h3>
      ${shippingHistories.map(history => `
        <div class="admin-order-grid">
          <div>
            <span>تكلفة الشحن</span>
            <strong>${Number(history.new_shipping_cost || 0).toLocaleString('ar-EG')} جنيه</strong>
          </div>
          <div>
            <span>المسؤول</span>
            <strong>${escapeAdminHtml(history.admin?.full_name || history.admin?.name || '—')}</strong>
          </div>
          <div>
            <span>تاريخ التسجيل</span>
            <strong>${escapeAdminHtml(history.created_at ? new Date(history.created_at).toLocaleString('ar-EG') : '—')}</strong>
          </div>
          <div>
            <span>الملاحظات الداخلية</span>
            <strong>${escapeAdminHtml(history.internal_notes || 'لا توجد ملاحظات')}</strong>
          </div>
        </div>
      `).join('')}
    </section>
  `
  : '';
    const shippingCostForm = order.status === 'pending_shipping_cost'
  ? `
    <section class="panel admin-order-detail">
      <h3>تحديد تكلفة الشحن</h3>
      <form id="adminShippingCostForm">
        <label for="adminShippingCost">تكلفة الشحن بالجنيه</label>
        <input
          type="number"
          id="adminShippingCost"
          name="shipping_cost"
          min="0"
          max="999999999"
          step="0.01"
          required
          placeholder="أدخل تكلفة الشحن"
        >

        <label for="adminShippingNotes">ملاحظات داخلية (اختياري)</label>
        <textarea
          id="adminShippingNotes"
          name="internal_notes"
          maxlength="2000"
          placeholder="ملاحظات للإدارة فقط"
        ></textarea>

        <button type="submit" class="primary">حفظ تكلفة الشحن</button>
      </form>
    </section>
  `
  : '';
  app.innerHTML = shell(
      'تفاصيل الطلب',
      'مراجعة بيانات العميل والطلب والموردين وحالة العربون.',
      `
        <div class="admin-orders-toolbar">
          <button class="outline" type="button" data-admin-orders-action="back">العودة إلى قائمة الطلبات</button>
        </div>
        ${orderStatusAction}
        <section class="panel admin-order-detail">
          <h3>الطلب رقم: ${escapeAdminHtml(order.order_number || order.id)}</h3>
          <div class="admin-order-grid">
            <div><span>حالة الطلب</span><strong>${escapeAdminHtml(statusLabel)}</strong></div>
            <div><span>تاريخ الإنشاء</span><strong>${escapeAdminHtml(order.created_at ? new Date(order.created_at).toLocaleString('ar-EG') : '—')}</strong></div>
            <div><span>اسم العميل</span><strong>${escapeAdminHtml(buyer.full_name || buyer.name || '—')}</strong></div>
            <div><span>هاتف العميل</span><strong>${escapeAdminHtml(buyer.phone || '—')}</strong></div>
            <div><span>البريد الإلكتروني</span><strong>${escapeAdminHtml(buyer.email || '—')}</strong></div>
            <div><span>اسم المستلم</span><strong>${escapeAdminHtml(order.recipient_name || '—')}</strong></div>
            <div><span>العنوان</span><strong>${escapeAdminHtml(order.address || '—')}</strong></div>
            <div><span>إجمالي الطلب</span><strong>${Number(order.grand_total || 0).toLocaleString('ar-EG')} جنيه</strong></div>
            <div><span>العربون 30%</span><strong>${Number(order.deposit_amount || 0).toLocaleString('ar-EG')} جنيه</strong></div>
            <div><span>المتبقي 70%</span><strong>${Number(order.balance_amount || 0).toLocaleString('ar-EG')} جنيه</strong></div>
          </div>
        </section>
        <section class="panel admin-order-detail">
          <h3>بيانات العربون</h3>
          <p><strong>حالة الإيصال:</strong> ${escapeAdminHtml(payment?.status || (order.receipt_uploaded ? 'مرفوع' : 'لم يتم رفع إيصال'))}</p>
          <p><strong>طريقة الدفع:</strong> ${escapeAdminHtml(payment?.payment_method || '—')}</p>
          <p><strong>مرجع التحويل:</strong> ${escapeAdminHtml(payment?.transaction_reference || '—')}</p>
          <p><strong>ملاحظة الإدارة:</strong> ${escapeAdminHtml(payment?.review_note || 'لا توجد ملاحظات')}</p>
          <p class="hint">هذه الصفحة للعرض فقط. مراجعة الإيصال تتم من صفحة مراجعة المدفوعات.</p>
        </section>
        <h2>تفاصيل الموردين والأصناف</h2>
        ${supplierSections}
      ${shippingCostForm}
        ${balanceSection}
        ${shippingHistorySection}
        `
    );
      const shippingForm = document.getElementById('adminShippingCostForm');

    if (shippingForm) {
      shippingForm.addEventListener('submit', async e => {
        e.preventDefault();

        const submitButton = shippingForm.querySelector('button[type="submit"]');
        const formData = new FormData(shippingForm);

        const payload = {
          shipping_cost: Number(formData.get('shipping_cost')),
          internal_notes: String(formData.get('internal_notes') || '').trim()
        };

        submitButton.disabled = true;
        submitButton.textContent = 'جارٍ حفظ تكلفة الشحن...';

        try {
          await apiRequest(
            '/admin/orders/' + encodeURIComponent(order.id) + '/shipping-cost',
            {
              method: 'PATCH',
              body: JSON.stringify(payload)
            }
          );

          notify('تم حفظ تكلفة الشحن بنجاح');

          await openAdminOrder(order.id);
        } catch (error) {
          notify(error.message);
          submitButton.disabled = false;
          submitButton.textContent = 'حفظ تكلفة الشحن';
        }
      });
    }

    const collectBalanceForm = document.getElementById('adminCollectBalanceForm');

    if (collectBalanceForm) {
      collectBalanceForm.addEventListener('submit', async e => {
        e.preventDefault();

        const submitButton = collectBalanceForm.querySelector('button[type="submit"]');
        const formData = new FormData(collectBalanceForm);

        const payload = {
          amount: Number(formData.get('amount')),
          payment_method: String(formData.get('payment_method') || '')
        };

        submitButton.disabled = true;
        submitButton.textContent = 'جارٍ تسجيل التحصيل...';

        try {
          await apiRequest(
            '/admin/orders/' + encodeURIComponent(order.id) + '/collect-balance',
            {
              method: 'POST',
              body: JSON.stringify(payload)
            }
          );

          notify('تم تسجيل التحصيل وإتمام الطلب بنجاح');
          await openAdminOrder(order.id);
        } catch (error) {
          notify(error.message);
          submitButton.disabled = false;
          submitButton.textContent = 'تسجيل التحصيل وإتمام الطلب';
        }
      });
    }
  }

  async function loadMyOrders() {
    const container = document.getElementById('myOrdersList');
    if (!container) return;

    container.innerHTML = '<div class="empty">جارٍ تحميل طلباتك...</div>';

    try {
      const result = await apiRequest('/orders');
      const orders = Array.isArray(result.orders) ? result.orders : [];

      if (!orders.length) {
        container.innerHTML = `
          <div class="panel empty">
            <h3>لا توجد طلبات حتى الآن</h3>
            <p>عندما تنشئ طلبًا من السوق الزراعي سيظهر هنا.</p>
            <button class="primary" data-page="market" type="button">تصفح السوق</button>
          </div>`;
        return;
      }

      const labels = {
        pending_payment: 'بانتظار العربون',
        pending_shipping_cost: 'بانتظار تحديد تكلفة الشحن',
        confirmed: 'تم تأكيد الطلب',
        processing: 'قيد التجهيز',
        shipped: 'تم الشحن',
        delivered: 'تم التسليم',
        cancelled: 'ملغي',
        completed: 'مكتمل'
      };

      container.innerHTML = orders.map(order => {
        const payment = order.deposit_transaction || null;
        let receiptStatus = 'بانتظار العربون';

if (payment?.status === 'rejected') {
  receiptStatus = 'تم رفض الإيصال';
} else if (payment?.status === 'approved') {
  receiptStatus = 'تم اعتماد العربون';
} else if (order.receipt_uploaded) {
  receiptStatus = 'يتم تأكيد سلامة الإيصال';
}

        const createdAt = order.created_at
          ? new Date(order.created_at).toLocaleString('ar-EG')
          : '—';
        const orderStatus = payment?.status === 'rejected'
  ? 'تم رفض الإيصال'
  : payment?.status === 'approved'
    ? 'تم اعتماد العربون'
    : order.receipt_uploaded
      ? 'يتم تأكيد سلامة الإيصال'
      : labels[order.status] || order.status || 'غير محدد';
        const shippingPending = order.status === 'pending_shipping_cost';

        const totalDisplay = shippingPending
          ? 'بانتظار تحديد الشحن'
          : `${Number(order.grand_total || 0).toLocaleString('ar-EG')} جنيه`;

        const depositDisplay = shippingPending
          ? 'يُحدد بعد الشحن'
          : `${Number(order.deposit_amount || 0).toLocaleString('ar-EG')} جنيه`;

        const balanceDisplay = shippingPending
          ? 'يُحدد بعد الشحن'
          : `${Number(order.balance_amount || 0).toLocaleString('ar-EG')} جنيه`;

        const statusClass = ['confirmed', 'delivered', 'completed'].includes(order.status)
          ? 'ok' : '';
  
        return `
          <article class="panel my-order-card">
            <div class="my-order-heading">
              <div>
                <span class="hint">رقم الطلب</span>
                <h3>${escapeAdminHtml(order.order_number || order.id)}</h3>
              </div>
              <span class="status ${statusClass}">${escapeAdminHtml(orderStatus)}</span>
            </div>

            <div class="my-order-grid">
              <div><span>تاريخ الطلب</span><strong>${escapeAdminHtml(createdAt)}</strong></div>
              <div><span>إجمالي الطلب</span><strong>${escapeAdminHtml(totalDisplay)}</strong></div>
              <div><span>العربون (30%)</span><strong>${escapeAdminHtml(depositDisplay)}</strong></div>
              <div><span>المتبقي عند الاستلام</span><strong>${escapeAdminHtml(balanceDisplay)}</strong></div>
            </div>

            <p class="my-order-receipt">
              <strong>حالة الإيصال:</strong> ${escapeAdminHtml(receiptStatus)}
            </p>

            <button class="outline" type="button"
              data-my-order-id="${escapeAdminHtml(order.id)}">عرض التفاصيل</button>
          </article>`;
      }).join('');

    } catch (error) {
      console.error('My orders error:', error);
      container.innerHTML =
        '<div class="panel empty">تعذر تحميل طلباتك: ' +
        escapeAdminHtml(error.message) +
        '<br><button class="outline" data-my-orders-action="refresh">إعادة المحاولة</button></div>';
    }
  }

  function renderMyOrders() {
    if (!state.token || !state.user || state.user.role !== 'buyer') {
      app.innerHTML = shell(
        'طلباتي',
        'سجّل الدخول بحساب مشتري لعرض طلباتك.',
        '<div class="panel"><p>هذه الصفحة متاحة لحسابات المشترين.</p><button class="primary" data-page="login">تسجيل الدخول</button></div>'
      );
      return;
    }

    app.innerHTML = shell(
      'طلباتي',
      'تابع طلباتك وحالة العربون والإيصالات من مكان واحد.',
      '<div class="my-orders-toolbar"><p>طلباتك المسجلة على GOZORX.</p><button class="outline" data-my-orders-action="refresh">تحديث الطلبات</button></div><div id="myOrdersList"><div class="empty">جارٍ تحميل طلباتك...</div></div>'
    );

    loadMyOrders();
  }

  async function openMyOrder(orderId, button) {
    if (!state.token || !state.user || state.user.role !== 'buyer') {
      notify('هذه الصفحة متاحة لحسابات المشترين فقط.');
      return;
    }

    if (button) {
      button.disabled = true;
      button.textContent = 'جارٍ تحميل التفاصيل...';
    }

    try {
      const result = await apiRequest('/orders/' + encodeURIComponent(orderId));
      state.currentOrder = result.order;
      go('order-details');
    } catch (error) {
      notify(error.message);
      if (button) {
        button.disabled = false;
        button.textContent = 'عرض التفاصيل';
      }
    }
  }


  const supplierDocNames = {
    commercial_register: 'السجل التجاري',
    tax_card: 'البطاقة الضريبية',
    personal_photo: 'الصورة الشخصية',
    national_id: 'بطاقة الرقم القومي'
  };

  const supplierStatusNames = {
    pending_approval: 'بانتظار المراجعة',
    approved: 'مورد معتمد',
    rejected: 'مرفوض',
    suspended: 'موقوف'
  };


  const productImageUrl = path =>
    API_BASE.replace(/\/api\/v1\/?$/, '') + '/storage/' +
    String(path || '').split('/').map(encodeURIComponent).join('/');

  async function loadAdminProducts() {
    const box = document.getElementById('adminProductsList');
    if (!box) return;

    box.innerHTML = '<div class="empty">جارٍ تحميل المنتجات...</div>';

    try {
      const result = await apiRequest('/admin/products');
      const products = Array.isArray(result.data) ? result.data : [];

      if (!products.length) {
        box.innerHTML = '<div class="panel empty">لا توجد منتجات تنتظر المراجعة حاليًا.</div>';
        return;
      }

      box.innerHTML = products.map(product => {
        const supplier = product.supplier || {};
        const user = supplier.user || {};

        const adminProductImages = Array.isArray(product.images)
          ? product.images
          : [];

        const adminImagesMarkup = adminProductImages.length
          ? `<div class="admin-product-images">
              ${adminProductImages.map((image, index) => `
                <div class="admin-product-image-item">
                  <img
                    src="${escapeAdminHtml(productImageUrl(image.image_path))}"
                    alt="صورة المنتج ${index + 1}"
                    loading="lazy"
                  >
                  ${image.is_primary || index === 0
                    ? '<span class="admin-primary-image-label">الصورة الأساسية</span>'
                    : `<span class="admin-image-number">الصورة ${index + 1}</span>`}
                </div>
              `).join('')}
            </div>`
          : product.image_path
            ? `<div class="admin-product-images">
                <div class="admin-product-image-item">
                  <img src="${escapeAdminHtml(productImageUrl(product.image_path))}" alt="صورة المنتج" loading="lazy">
                  <span class="admin-primary-image-label">الصورة الأساسية</span>
                </div>
              </div>`
            : '<p class="hint">لم يرفق المورد صورًا لهذا المنتج.</p>';

        return `
          <article class="panel admin-order-card">
            <div class="admin-order-heading">
              <h3>${escapeAdminHtml(product.name_ar || product.name_en || 'منتج')}</h3>
              <span class="status">بانتظار المراجعة</span>
            </div>
            ${adminImagesMarkup}
            <div class="admin-order-grid">
              <div><span>رقم المنتج</span><strong>${escapeAdminHtml(product.id)}</strong></div>
              <div><span>اسم المنتج بالإنجليزية</span><strong>${escapeAdminHtml(product.name_en || '—')}</strong></div>
              <div><span>السعر</span><strong>${escapeAdminHtml(product.unit_price)} جنيه</strong></div>
              <div><span>الوحدة</span><strong>${escapeAdminHtml(product.unit_name || '—')}</strong></div>
              <div><span>الحد الأدنى للطلب</span><strong>${escapeAdminHtml(product.minimum_order_quantity)}</strong></div>
              <div><span>الكمية المتاحة</span><strong>${escapeAdminHtml(product.available_quantity)}</strong></div>
              <div><span>المورد</span><strong>${escapeAdminHtml(supplier.business_name || '—')}</strong></div>
              <div><span>اسم صاحب الحساب</span><strong>${escapeAdminHtml(user.full_name || '—')}</strong></div>
              <div><span>هاتف المورد</span><strong>${escapeAdminHtml(user.phone || '—')}</strong></div>
            </div>
            ${product.description ? `<p>${escapeAdminHtml(product.description)}</p>` : ''}
            <div class="admin-product-actions">
              <button class="primary" type="button" data-admin-product-action="approve" data-product-id="${escapeAdminHtml(product.id)}">اعتماد ونشر المنتج</button>
              <button class="outline danger" type="button" data-admin-product-action="reject" data-product-id="${escapeAdminHtml(product.id)}">رفض المنتج</button>
            </div>
          </article>`;
      }).join('');
    } catch (error) {
      box.innerHTML = '<div class="panel empty">تعذر تحميل المنتجات: ' +
        escapeAdminHtml(error.message) +
        '<br><button class="outline" data-admin-products-action="refresh">إعادة المحاولة</button></div>';
    }
  }

  function renderAdminProducts() {
    if (!state.token || !state.user || state.user.role !== 'admin') {
      app.innerHTML = shell('غير مصرح بالدخول', 'هذه الصفحة للإدارة فقط.',
        '<div class="panel"><button class="primary" data-page="login">تسجيل الدخول</button></div>');
      return;
    }

    app.innerHTML = shell('مراجعة المنتجات', 'راجع بيانات المنتج والمورد قبل اعتماد المنتج ونشره في السوق.', `
      <div class="admin-orders-toolbar panel">
        <p>تظهر هنا المنتجات الجديدة التي أضافها الموردون وتنتظر مراجعة الإدارة.</p>
        <button class="outline" type="button" data-admin-products-action="refresh">تحديث القائمة</button>
      </div>
      <div id="adminProductsList"><div class="empty">جارٍ التحميل...</div></div>
    `);
    loadAdminProducts();
  }

  async function handleAdminProductDecision(action, id, button) {
    if (!['approve', 'reject'].includes(action)) return;

    let reviewNote = null;

    if (action === 'reject') {
      reviewNote = window.prompt('اكتب سبب رفض المنتج ليظهر ضمن سجل المراجعة:');
      if (reviewNote === null) return;
      reviewNote = reviewNote.trim();
    } else if (!window.confirm('هل تريد اعتماد هذا المنتج ونشره في السوق؟')) {
      return;
    }

    if (button) {
      button.disabled = true;
      button.textContent = 'جارٍ تنفيذ القرار...';
    }

    try {
      await apiRequest('/admin/products/' + encodeURIComponent(id) + '/decision', {
        method: 'PATCH',
        body: JSON.stringify({
          decision: action === 'approve' ? 'approved' : 'rejected',
          review_note: reviewNote
        })
      });

      notify(action === 'approve' ? 'تم اعتماد المنتج بنجاح ونشره في السوق' : 'تم رفض المنتج وتسجيل سبب الرفض');
      await loadAdminProducts();
    } catch (error) {
      notify(error.message);
      if (button) {
        button.disabled = false;
        button.textContent = action === 'approve' ? 'اعتماد ونشر المنتج' : 'رفض المنتج';
      }
    }
  }

  async function loadAdminSuppliers() {
    const box = document.getElementById('adminSuppliersList');
    if (!box) return;

    const status = document.getElementById('adminSuppliersStatus')?.value || 'pending_approval';
    box.innerHTML = '<div class="empty">جارٍ تحميل الموردين...</div>';

    try {
      const result = await apiRequest('/admin/suppliers?status=' + encodeURIComponent(status));
      const suppliers = Array.isArray(result.data) ? result.data : [];

      if (!suppliers.length) {
        box.innerHTML = '<div class="panel empty">لا توجد طلبات بهذه الحالة.</div>';
        return;
      }

      box.innerHTML = suppliers.map(s => {
        const u = s.user || {};
        return `
          <article class="panel admin-order-card">
            <div class="admin-order-heading">
              <h3>${escapeAdminHtml(s.business_name || 'مورد')} - ${escapeAdminHtml(s.id)}</h3>
              <span class="status">${escapeAdminHtml(supplierStatusNames[s.status] || s.status)}</span>
            </div>
            <div class="admin-order-grid">
              <div><span>مقدم الطلب</span><strong>${escapeAdminHtml(u.full_name || '—')}</strong></div>
              <div><span>الهاتف</span><strong>${escapeAdminHtml(u.phone || '—')}</strong></div>
              <div><span>البريد</span><strong>${escapeAdminHtml(u.email || '—')}</strong></div>
              <div><span>تاريخ التقديم</span><strong>${escapeAdminHtml(s.submitted_at ? new Date(s.submitted_at).toLocaleString('ar-EG') : '—')}</strong></div>
            </div>
            <button class="outline" data-admin-supplier-id="${escapeAdminHtml(s.id)}">مراجعة الطلب والمستندات</button>
          </article>`;
      }).join('');
    } catch (error) {
      box.innerHTML = '<div class="panel empty">تعذر تحميل الموردين: ' +
        escapeAdminHtml(error.message) +
        '<br><button class="outline" data-admin-suppliers-action="refresh">إعادة المحاولة</button></div>';
    }
  }

  function renderAdminSuppliers() {
    if (!state.token || !state.user || state.user.role !== 'admin') {
      app.innerHTML = shell('غير مصرح بالدخول', 'هذه الصفحة للإدارة فقط.',
        '<div class="panel"><button class="primary" data-page="login">تسجيل الدخول</button></div>');
      return;
    }

    app.innerHTML = shell('إدارة الموردين', 'مراجعة طلبات الانضمام ومستندات التحقق.', `
      <div class="admin-orders-toolbar panel">
        <div class="admin-orders-filters">
          <select id="adminSuppliersStatus">
            <option value="pending_approval">طلبات تنتظر المراجعة</option>
            <option value="approved">الموردون المعتمدون</option>
            <option value="rejected">الطلبات المرفوضة</option>
            <option value="suspended">الموردون الموقوفون</option>
          </select>
          <button class="primary" data-admin-suppliers-action="search">عرض</button>
          <button class="outline" data-admin-suppliers-action="refresh">تحديث</button>
        </div>
      </div>
      <div id="adminSuppliersList"><div class="empty">جارٍ التحميل...</div></div>
    `);
    loadAdminSuppliers();
  }

  async function openAdminSupplier(id) {
    app.innerHTML = shell('تفاصيل المورد', 'جارٍ تحميل البيانات...', '<div class="panel empty">يرجى الانتظار...</div>');
    try {
      const result = await apiRequest('/admin/suppliers/' + encodeURIComponent(id));
      state.currentAdminSupplier = result.supplier;
      go('admin-supplier-details');
    } catch (error) {
      notify(error.message);
      go('admin-suppliers');
    }
  }

  function renderAdminSupplierDetails() {
    const s = state.currentAdminSupplier;
    if (!s) {
      app.innerHTML = shell('تفاصيل المورد', '', '<button class="outline" data-page="admin-suppliers">العودة</button>');
      return;
    }

    const u = s.user || {};
    const mapUrl = s.latitude && s.longitude
      ? 'https://www.google.com/maps?q=' + encodeURIComponent(s.latitude + ',' + s.longitude)
      : '';
    const docs = (s.documents || []).map(d => {
      const pending = d.status === 'pending_review';
      return `
        <article class="panel">
          <div class="admin-order-heading">
            <h3>${escapeAdminHtml(supplierDocNames[d.document_type] || d.document_type)}</h3>
            <span class="status">${escapeAdminHtml(d.status === 'approved' ? 'مقبول' : d.status === 'rejected' ? 'مرفوض - مطلوب إعادة الرفع' : 'بانتظار المراجعة')}</span>
          </div>
          <p>الملف: ${escapeAdminHtml(d.original_filename || '—')}</p>
          ${d.review_note ? `<p>ملاحظة الإدارة: ${escapeAdminHtml(d.review_note)}</p>` : ''}
          <button class="outline" data-admin-supplier-doc-action="view" data-document-id="${d.id}">عرض المستند</button>
          ${pending ? `
            <button class="primary" data-admin-supplier-doc-action="approve" data-document-id="${d.id}">قبول المستند</button>
            <button class="outline" data-admin-supplier-doc-action="reject" data-document-id="${d.id}">رفض وطلب إعادة الرفع</button>
          ` : ''}
        </article>`;
    }).join('');

    app.innerHTML = shell('مراجعة طلب المورد', 'اعتماد المورد يتم تلقائيًا بعد قبول المستندات الأربعة.', `
      <div class="panel">
        <h2>${escapeAdminHtml(s.business_name || '—')}</h2>
        <p>حالة الطلب: ${escapeAdminHtml(supplierStatusNames[s.status] || s.status)}</p>
        <div class="admin-order-grid">
          <div><span>مقدم الطلب</span><strong>${escapeAdminHtml(u.full_name || '—')}</strong></div>
          <div><span>نوع النشاط</span><strong>${escapeAdminHtml(s.business_type || '—')}</strong></div>
          <div><span>الهاتف</span><strong>${escapeAdminHtml(u.phone || '—')}</strong></div>
          <div><span>البريد</span><strong>${escapeAdminHtml(u.email || '—')}</strong></div>
          <div><span>العنوان</span><strong>${escapeAdminHtml(s.address || '—')}</strong></div>
        </div>
        ${s.description ? `<p>وصف النشاط: ${escapeAdminHtml(s.description)}</p>` : ''}
        ${mapUrl ? `<p><a href="${mapUrl}" target="_blank" rel="noopener noreferrer">فتح موقع المورد على الخريطة</a></p>` : ''}
      </div>
      <h2>مستندات التحقق</h2>
      ${docs || '<div class="panel empty">لا توجد مستندات.</div>'}
      <div class="panel"><button class="outline" data-admin-suppliers-action="back">العودة إلى قائمة الموردين</button></div>
    `);
  }

  async function handleAdminSupplierDocumentAction(action, id, button) {
    const s = state.currentAdminSupplier;
    const doc = (s?.documents || []).find(d => String(d.id) === String(id));
    if (!doc) return;

    if (action === 'view') {
      const tab = window.open('about:blank', '_blank');
      if (!tab) {
        notify('اسمح بالنوافذ المنبثقة لعرض المستند.');
        return;
      }
      try {
        const response = await fetch(API_BASE + '/admin/supplier-documents/' + encodeURIComponent(id) + '/file', {
          headers: { 'Authorization': 'Bearer ' + state.token, 'Accept': 'application/pdf,image/jpeg,image/png' }
        });
        if (!response.ok) throw new Error('تعذر فتح المستند.');
        const url = URL.createObjectURL(await response.blob());
        tab.location.href = url;
        setTimeout(() => URL.revokeObjectURL(url), 60000);
      } catch (error) {
        tab.close();
        notify(error.message);
      }
      return;
    }

    if (doc.status !== 'pending_review') {
      notify('تمت مراجعة هذا المستند بالفعل.');
      return;
    }

    let note = '';
    if (action === 'reject') {
      note = window.prompt('اكتب سبب رفض المستند وما المطلوب تعديله:') || '';
      if (!note.trim()) {
        notify('سبب الرفض مطلوب.');
        return;
      }
    } else if (action !== 'approve') {
      return;
    }

    if (button) button.disabled = true;
    try {
      await apiRequest('/admin/supplier-documents/' + encodeURIComponent(id) + '/decision', {
        method: 'PATCH',
        body: JSON.stringify({
          decision: action === 'approve' ? 'approved' : 'rejected',
          review_note: note
        })
      });
      notify(action === 'approve' ? 'تم قبول المستند.' : 'تم رفض المستند وحفظ سبب الرفض.');
      await openAdminSupplier(s.id);
    } catch (error) {
      notify(error.message);
      if (button) button.disabled = false;
    }
  }

  async function renderSupplierApplication() {
    if (!state.token || !state.user || state.user.role !== 'supplier') {
      app.innerHTML = shell(
        'متابعة طلب المورد',
        'هذه الصفحة مخصصة للحسابات المسجلة بدور المورد فقط.',
        '<div class="panel"><p>للوصول إلى متابعة طلب المورد، يرجى تسجيل الدخول بحساب المورد المستقل.</p><button class="outline" data-page="home">العودة إلى السوق</button></div>'
      );
      return;
    }

    app.innerHTML = shell(
      'متابعة طلب المورد',
      'جارٍ تحميل حالة الطلب والمستندات...',
      '<div class="panel empty">يرجى الانتظار...</div>'
    );

    try {
      const result = await apiRequest('/supplier/application');
      const supplier = result.supplier || {};

      const documentStatusNames = {
        pending_review: 'قيد المراجعة',
        approved: 'معتمد',
        rejected: 'مرفوض — مطلوب إعادة الرفع'
      };

      const formatDate = value => {
        if (!value) return '—';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? '—' : date.toLocaleString('ar-EG');
      };

      const documents = (supplier.documents || []).map(doc => {
        const rejected = doc.status === 'rejected';
        const imageOnly = ['personal_photo', 'national_id'].includes(doc.type);
        const accept = imageOnly
          ? 'image/jpeg,image/png'
          : '.pdf,image/jpeg,image/png';

        return `
          <article class="panel">
            <div class="admin-order-heading">
              <h3>${escapeAdminHtml(supplierDocNames[doc.type] || doc.type)}</h3>
              <span class="status">${escapeAdminHtml(documentStatusNames[doc.status] || doc.status)}</span>
            </div>
            <p class="hint">آخر إرسال: ${escapeAdminHtml(formatDate(doc.submitted_at))}</p>
            ${doc.reviewed_at ? `<p class="hint">تاريخ المراجعة: ${escapeAdminHtml(formatDate(doc.reviewed_at))}</p>` : ''}
            ${doc.review_note ? `<p class="payment-notice"><strong>ملاحظة الإدارة:</strong> ${escapeAdminHtml(doc.review_note)}</p>` : ''}
            ${rejected ? `
              <form class="form-grid" data-supplier-reupload="${escapeAdminHtml(doc.type)}">
                <div class="field full">
                  <label>اختر نسخة جديدة من المستند</label>
                  <input type="file" name="document" accept="${accept}" required>
                  <p class="hint">${imageOnly ? 'الصيغ المقبولة: JPG أو PNG' : 'الصيغ المقبولة: PDF أو JPG أو PNG'}</p>
                </div>
                <div class="field full">
                  <button class="primary" type="submit">إعادة رفع المستند</button>
                </div>
              </form>
            ` : ''}
          </article>
        `;
      }).join('');

      app.innerHTML = shell(
        'متابعة طلب المورد',
        'يمكنك متابعة مراجعة المستندات ومعرفة المطلوب لاستكمال اعتمادك.',
        `
          <div class="panel">
            <h2>${escapeAdminHtml(supplier.business_name || 'طلب المورد')}</h2>
            <p>حالة الطلب: <strong>${escapeAdminHtml(supplierStatusNames[supplier.status] || supplier.status || '—')}</strong></p>
            ${supplier.status === 'approved' ? `
              <div class="payment-notice">
                <strong>تم اعتماد حسابك كمورد!</strong>
                <p>يمكنك الآن إضافة منتجاتك إلى المنصة، وستُراجع الإدارة كل منتج قبل نشره.</p>
                <button class="primary" data-page="supplier-add-product" type="button">إضافة منتج جديد</button>
              </div>
            ` : ''}
            <p>تاريخ تقديم الطلب: ${escapeAdminHtml(formatDate(supplier.submitted_at))}</p>
            <p>العنوان: ${escapeAdminHtml(supplier.address || '—')}</p>
          </div>
          <h2>مستندات التحقق</h2>
          ${documents || '<div class="panel empty">لا توجد مستندات مسجلة لهذا الطلب.</div>'}
          <div class="panel">
            <button class="outline" data-supplier-application-action="refresh">تحديث الحالة</button>
            <button class="outline" data-page="home">العودة للرئيسية</button>
          </div>
        `
      );

      app.querySelectorAll('form[data-supplier-reupload]').forEach(form => {
        form.addEventListener('submit', async e => {
          e.preventDefault();

          const button = form.querySelector('button[type="submit"]');
          const type = form.dataset.supplierReupload;

          if (!form.elements.namedItem('document')?.files?.length) {
            notify('اختر الملف الجديد أولًا.');
            return;
          }

          button.disabled = true;
          button.textContent = 'جارٍ رفع المستند...';

          try {
            const formData = new FormData(form);
            const response = await apiRequest(
              '/supplier/documents/' + encodeURIComponent(type) + '/reupload',
              { method: 'POST', body: formData }
            );

            notify(response.message || 'تم رفع المستند بنجاح وإرساله للمراجعة.');
            await renderSupplierApplication();
          } catch (error) {
            notify(error.message || 'تعذر إعادة رفع المستند.');
            button.disabled = false;
            button.textContent = 'إعادة رفع المستند';
          }
        });
      });
    } catch (error) {
      const noApplication = error.message === 'لا يوجد طلب مورد مرتبط بهذا الحساب.';

      app.innerHTML = shell(
        'متابعة طلب المورد',
        noApplication
          ? 'لا يوجد طلب مورد مرتبط بهذا الحساب حتى الآن.'
          : 'تعذر تحميل بيانات طلب المورد.',
        `
          <div class="panel">
            <p>${escapeAdminHtml(error.message)}</p>
            ${noApplication
              ? '<button class="primary" data-page="join">تقديم طلب انضمام كمورد</button>'
              : '<button class="primary" data-supplier-application-action="refresh">إعادة المحاولة</button>'}
          </div>
        `
      );
    }
  }


  async function renderSupplierAddProduct() {
    if (!state.token || !state.user) {
      app.innerHTML = shell(
        'إضافة منتج جديد',
        'سجّل الدخول أولًا.',
        '<div class="panel"><button class="primary" data-page="login">تسجيل الدخول</button></div>'
      );
      return;
    }

    app.innerHTML = shell(
      'إضافة منتج جديد',
      'جارٍ التحقق من اعتماد حساب المورد...',
      '<div class="panel empty">يرجى الانتظار...</div>'
    );

    try {
      const application = await apiRequest('/supplier/application');
      const supplier = application.supplier || {};

      if (supplier.status !== 'approved') {
        app.innerHTML = shell(
          'إضافة منتج جديد',
          'إضافة المنتجات متاحة للموردين المعتمدين فقط.',
          `<div class="panel">
            <p>حالة حسابك: <strong>${escapeAdminHtml(supplierStatusNames[supplier.status] || supplier.status || 'غير معروفة')}</strong></p>
            <button class="outline" data-page="supplier-application">متابعة طلب المورد</button>
          </div>`
        );
        return;
      }

      const categoryResponse = await apiRequest('/categories');
      const flatten = (items, level = 0) => {
        let result = [];
        (items || []).forEach(category => {
          result.push({ ...category, level });
          if (Array.isArray(category.children) && category.children.length) {
            result = result.concat(flatten(category.children, level + 1));
          }
        });
        return result;
      };

      const categoriesList = flatten(categoryResponse.data || []);
      const categoryOptions = categoriesList.map(category =>
        `<option value="${escapeAdminHtml(category.id)}">${'— '.repeat(category.level)}${escapeAdminHtml(category.name_ar || category.name_en || 'قسم')}</option>`
      ).join('');

      app.innerHTML = shell(
        'إضافة منتج جديد',
        'أدخل بيانات المنتج بدقة. سيُرسل المنتج إلى الإدارة للمراجعة قبل ظهوره في السوق.',
        `
          <form class="panel form-grid" id="supplierProductForm">
            <div class="field">
              <label>اسم المنتج بالعربية *</label>
              <input name="name_ar" type="text" maxlength="220" required>
            </div>
            <div class="field">
              <label>اسم المنتج بالإنجليزية</label>
              <input name="name_en" type="text" maxlength="220">
            </div>
            <div class="field full">
              <label>القسم</label>
              <select name="category_id">
                <option value="">اختر القسم (اختياري)</option>
                ${categoryOptions}
              </select>
            </div>
            <div class="field full">
              <label>وصف المنتج</label>
              <textarea name="description" rows="4"></textarea>
            </div>
            <div class="field full">
              <label>صور المنتج (حتى 5 صور)</label>
              <input name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
              <div class="supplier-images-preview" id="supplierProductImagesPreview">
                <span>لم يتم اختيار صور بعد</span>
              </div>
              <p class="hint">يمكنك اختيار حتى 5 صور. الصيغ JPG وPNG وWebP، بحد أقصى 5 ميجابايت للصورة.</p>
            </div>
            <div class="field">
              <label>وحدة البيع *</label>
              <input name="unit_name" type="text" maxlength="50" placeholder="مثال: شتلة، أصيص، قطعة" required>
            </div>
            <div class="field">
              <label>سعر الوحدة بالجنيه *</label>
              <input name="unit_price" type="number" min="0" step="0.01" required>
            </div>
            <div class="field">
              <label>الحد الأدنى للطلب *</label>
              <input name="minimum_order_quantity" type="number" min="1" step="1" required>
            </div>
            <div class="field">
              <label>الكمية المتاحة *</label>
              <input name="available_quantity" type="number" min="0" step="1" required>
            </div>
            <div class="field full">
              <p class="hint">بعد الحفظ سيصبح المنتج مسودة، ولن يظهر للعملاء إلا بعد مراجعة الإدارة واعتماده.</p>
              <button class="primary" type="submit">حفظ المنتج وإرساله للمراجعة</button>
              <button class="outline" type="button" data-page="supplier-application">العودة لطلب المورد</button>
            </div>
          </form>
        `
      );

      const form = document.getElementById('supplierProductForm');

      const imagesInput = form.querySelector('input[name="images[]"]');
      const imagesPreview = document.getElementById('supplierProductImagesPreview');

      imagesInput.addEventListener('change', () => {
        const files = Array.from(imagesInput.files || []);
        const validTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (files.length > 5) {
          notify('يمكنك اختيار 5 صور كحد أقصى.');
          imagesInput.value = '';
          imagesPreview.innerHTML = '<span>لم يتم اختيار صور بعد</span>';
          return;
        }

        if (files.some(file => !validTypes.includes(file.type) || file.size > 5 * 1024 * 1024)) {
          notify('تأكد أن كل الصور JPG أو PNG أو WebP وبحجم لا يتجاوز 5 ميجابايت.');
          imagesInput.value = '';
          imagesPreview.innerHTML = '<span>لم يتم اختيار صور بعد</span>';
          return;
        }

        imagesPreview.replaceChildren();

        files.forEach(file => {
          const img = document.createElement('img');
          img.src = URL.createObjectURL(file);
          img.alt = 'معاينة صورة المنتج';
          imagesPreview.appendChild(img);
        });

        if (!files.length) {
          imagesPreview.innerHTML = '<span>لم يتم اختيار صور بعد</span>';
        }
      });
      form.addEventListener('submit', async event => {
        event.preventDefault();

        const button = form.querySelector('button[type="submit"]');
        const values = Object.fromEntries(new FormData(form).entries());

        const payload = {
          name_ar: values.name_ar.trim(),
          name_en: values.name_en.trim() || null,
          category_id: values.category_id ? Number(values.category_id) : null,
          description: values.description.trim() || null,
          unit_name: values.unit_name.trim(),
          unit_price: Number(values.unit_price),
          minimum_order_quantity: Number(values.minimum_order_quantity),
          available_quantity: Number(values.available_quantity)
        };

        if (
          !payload.name_ar ||
          !payload.unit_name ||
          !Number.isFinite(payload.unit_price) || payload.unit_price < 0 ||
          !Number.isInteger(payload.minimum_order_quantity) || payload.minimum_order_quantity < 1 ||
          !Number.isInteger(payload.available_quantity) || payload.available_quantity < 0
        ) {
          notify('راجع البيانات المدخلة وتأكد من صحة السعر والكميات.');
          return;
        }

        button.disabled = true;
        button.textContent = 'جارٍ حفظ المنتج...';

        try {
          const response = await apiRequest('/supplier/products', {
            method: 'POST',
            body: (() => {
              const formData = new FormData(form);

              Object.entries(payload).forEach(([key, value]) => {
                formData.set(key, value === null ? '' : String(value));
              });

              return formData;
            })()
          });

          const product = response.product || {};
          app.innerHTML = shell(
            'تم إرسال المنتج بنجاح',
            'تم حفظ المنتج كمسودة وهو الآن بانتظار مراجعة الإدارة.',
            `<div class="panel">
              <h2>${escapeAdminHtml(product.name_ar || payload.name_ar)}</h2>
              <p>حالة المنتج: <strong>مسودة — بانتظار مراجعة الإدارة</strong></p>
              <button class="primary" data-page="supplier-add-product">إضافة منتج آخر</button>
              <button class="outline" data-page="supplier-application">متابعة حساب المورد</button>
              <button class="outline" data-page="market">تصفح السوق</button>
            </div>`
          );
          notify(response.message || 'تم حفظ المنتج وإرساله للمراجعة.');
        } catch (error) {
          notify(error.message || 'تعذر حفظ المنتج.');
          button.disabled = false;
          button.textContent = 'حفظ المنتج وإرساله للمراجعة';
        }
      });
    } catch (error) {
      app.innerHTML = shell(
        'إضافة منتج جديد',
        'تعذر تحميل بيانات الإضافة.',
        `<div class="panel">
          <p>${escapeAdminHtml(error.message)}</p>
          <button class="outline" data-page="supplier-application">العودة لطلب المورد</button>
          <button class="primary" data-page="supplier-add-product">إعادة المحاولة</button>
        </div>`
      );
    }
  }



  async function renderSupplierProductEdit() {
    const productId = state.currentSupplierProductId;

    if (!state.token || !state.user || !productId) {
      app.innerHTML = shell(
        'تعديل المنتج',
        'تعذر تحديد المنتج المطلوب.',
        '<div class="panel"><button class="outline" data-page="supplier-products">العودة لمنتجاتي</button></div>'
      );
      return;
    }

    app.innerHTML = shell(
      'تعديل المنتج',
      'جارٍ تحميل بيانات المنتج...',
      '<div class="panel empty">يرجى الانتظار...</div>'
    );

    try {
      const application = await apiRequest('/supplier/application');
      const supplier = application.supplier || {};

      if (supplier.status !== 'approved') {
        app.innerHTML = shell(
          'تعديل المنتج',
          'هذه الصفحة متاحة للموردين المعتمدين فقط.',
          '<div class="panel"><button class="outline" data-page="supplier-application">متابعة طلب المورد</button></div>'
        );
        return;
      }

      const [productResponse, categoryResponse] = await Promise.all([
        apiRequest(`/supplier/products/${encodeURIComponent(productId)}`),
        apiRequest('/categories')
      ]);

      const product = productResponse.product;
      if (!product) throw new Error('لم يتم العثور على بيانات المنتج.');

      const existingImages = Array.isArray(product.images) ? product.images : [];
      const productImageUrl = path =>
        API_BASE.replace('/api/v1', '') + '/storage/' +
        String(path).split('/').map(encodeURIComponent).join('/');

      const existingImagesMarkup = existingImages.length
        ? existingImages.map(image =>
            `<div class="supplier-image-item" data-existing-image-id="${image.id}">
              <img src="${escapeAdminHtml(productImageUrl(image.image_path))}" alt="صورة المنتج الحالية">
              <button type="button" class="supplier-image-remove" data-remove-existing-image="${image.id}">حذف الصورة</button>
            </div>`
          ).join('')
        : product.image_path
          ? `<div class="supplier-image-item">
              <img src="${escapeAdminHtml(productImageUrl(product.image_path))}" alt="صورة المنتج الحالية">
              <span class="hint">الصورة القديمة ستتم إضافتها لسجل الصور عند أول حفظ.</span>
            </div>`
          : '<span>لا توجد صور حالية</span>';


      const flatten = (items, level = 0) => {
        let result = [];
        (items || []).forEach(category => {
          result.push({ ...category, level });
          if (Array.isArray(category.children) && category.children.length) {
            result = result.concat(flatten(category.children, level + 1));
          }
        });
        return result;
      };

      const categoriesList = flatten(categoryResponse.data || []);
      const categoryOptions = categoriesList.map(category =>
        `<option value="${escapeAdminHtml(category.id)}" ${Number(category.id) === Number(product.category_id) ? 'selected' : ''}>${'— '.repeat(category.level)}${escapeAdminHtml(category.name_ar || category.name_en || 'قسم')}</option>`
      ).join('');

      app.innerHTML = shell(
        'تعديل المنتج',
        'عدّل البيانات المطلوبة. بعد الحفظ سيعود المنتج لمسودة لمراجعة الإدارة من جديد.',
        `
          <form class="panel form-grid" id="supplierProductEditForm">
            <div class="field">
              <label>اسم المنتج بالعربية *</label>
              <input name="name_ar" type="text" maxlength="220" value="${escapeAdminHtml(product.name_ar || '')}" required>
            </div>
            <div class="field">
              <label>اسم المنتج بالإنجليزية</label>
              <input name="name_en" type="text" maxlength="220" value="${escapeAdminHtml(product.name_en || '')}">
            </div>
            <div class="field full">
              <label>القسم</label>
              <select name="category_id">
                <option value="">اختر القسم (اختياري)</option>
                ${categoryOptions}
              </select>
            </div>
            <div class="field full">
              <label>وصف المنتج</label>
              <textarea name="description" rows="4">${escapeAdminHtml(product.description || '')}</textarea>
            </div>
            <div class="field full">
              <label>صور المنتج الحالية والجديدة</label>
              <div class="supplier-images-preview" id="supplierProductImagesPreview">
                ${existingImagesMarkup}
              </div>
              <label>إضافة صور جديدة (اختياري)</label>
              <input name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
              <p class="hint">يمكنك إضافة صور حتى يصبح الإجمالي 5 صور. الصور الحالية ستظل محفوظة. الصيغ JPG وPNG وWebP، بحد أقصى 5 ميجابايت للصورة.</p>
            </div>
            <div class="field">
              <label>وحدة البيع *</label>
              <input name="unit_name" type="text" maxlength="50" value="${escapeAdminHtml(product.unit_name || '')}" required>
            </div>
            <div class="field">
              <label>سعر الوحدة بالجنيه *</label>
              <input name="unit_price" type="number" min="0" step="0.01" value="${escapeAdminHtml(product.unit_price ?? '')}" required>
            </div>
            <div class="field">
              <label>الحد الأدنى للطلب *</label>
              <input name="minimum_order_quantity" type="number" min="1" step="1" value="${escapeAdminHtml(product.minimum_order_quantity ?? '')}" required>
            </div>
            <div class="field">
              <label>الكمية المتاحة *</label>
              <input name="available_quantity" type="number" min="0" step="1" value="${escapeAdminHtml(product.available_quantity ?? '')}" required>
            </div>
            <div class="field full">
              <p class="hint">حفظ أي تعديل سيعيد حالة المنتج إلى مسودة، وسيحتاج إلى موافقة الإدارة قبل ظهوره للعملاء.</p>
              <button class="primary" type="submit">حفظ التعديلات وإرسالها للمراجعة</button>
              <button class="outline" type="button" data-page="supplier-products">إلغاء والعودة لمنتجاتي</button>
            </div>
          </form>
        `
      );

      const form = document.getElementById('supplierProductEditForm');

      const imagesInput = form.querySelector('input[name="images[]"]');
      const imagesPreview = document.getElementById('supplierProductImagesPreview');
      const deletedImageIds = new Set();

      const updateImageLimitHint = () => {
        const remainingExisting = existingImages.length - deletedImageIds.size;
        const remainingSlots = Math.max(0, 5 - remainingExisting);
        const hint = imagesInput.parentElement.querySelector('.hint');
        if (hint) {
          hint.textContent = `يمكنك إضافة ${remainingSlots} صور جديدة. الحد الأقصى 5 صور إجمالًا.`;
        }
      };

      imagesPreview.addEventListener('click', event => {
        const button = event.target.closest('[data-remove-existing-image]');
        if (!button) return;

        const imageId = String(button.dataset.removeExistingImage);
        const imageItem = button.closest('.supplier-image-item');

        if (deletedImageIds.has(imageId)) {
          deletedImageIds.delete(imageId);
          imageItem?.classList.remove('marked-for-deletion');
          button.textContent = 'حذف الصورة';
        } else {
          deletedImageIds.add(imageId);
          imageItem?.classList.add('marked-for-deletion');
          button.textContent = 'إلغاء الحذف';
        }

        updateImageLimitHint();
      });

      imagesInput.addEventListener('change', () => {
        const files = Array.from(imagesInput.files || []);
        const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
        const remainingSlots = 5 - (existingImages.length - deletedImageIds.size);

        if (files.length > remainingSlots) {
          notify(`يمكنك إضافة ${remainingSlots} صور فقط لهذا المنتج.`);
          imagesInput.value = '';
          imagesPreview.querySelectorAll('.new-image-preview').forEach(img => img.remove());
          return;
        }

        if (files.some(file => !validTypes.includes(file.type) || file.size > 5 * 1024 * 1024)) {
          notify('تأكد أن كل الصور JPG أو PNG أو WebP وبحجم لا يتجاوز 5 ميجابايت.');
          imagesInput.value = '';
          imagesPreview.querySelectorAll('.new-image-preview').forEach(img => img.remove());
          return;
        }

        imagesPreview.querySelectorAll('.new-image-preview').forEach(img => img.remove());

        files.forEach(file => {
          const img = document.createElement('img');
          img.className = 'new-image-preview';
          img.src = URL.createObjectURL(file);
          img.alt = 'معاينة صورة جديدة';
          imagesPreview.appendChild(img);
        });
      });

      form.addEventListener('submit', async event => {
        event.preventDefault();

        const button = form.querySelector('button[type="submit"]');
        const values = Object.fromEntries(new FormData(form).entries());

        const payload = {
          name_ar: values.name_ar.trim(),
          name_en: values.name_en.trim() || null,
          category_id: values.category_id ? Number(values.category_id) : null,
          description: values.description.trim() || null,
          unit_name: values.unit_name.trim(),
          unit_price: Number(values.unit_price),
          minimum_order_quantity: Number(values.minimum_order_quantity),
          available_quantity: Number(values.available_quantity)
        };

        if (
          !payload.name_ar ||
          !payload.unit_name ||
          !Number.isFinite(payload.unit_price) || payload.unit_price < 0 ||
          !Number.isInteger(payload.minimum_order_quantity) || payload.minimum_order_quantity < 1 ||
          !Number.isInteger(payload.available_quantity) || payload.available_quantity < 0
        ) {
          notify('راجع البيانات المدخلة وتأكد من صحة السعر والكميات.');
          return;
        }

        button.disabled = true;
        button.textContent = 'جارٍ حفظ التعديلات...';

        try {
          const formData = new FormData(form);
          formData.set('_method', 'PUT');

          deletedImageIds.forEach(id => {
            formData.append('delete_image_ids[]', id);
          });

          Object.entries(payload).forEach(([key, value]) => {
            formData.set(key, value === null ? '' : String(value));
          });

          const response = await apiRequest(`/supplier/products/${encodeURIComponent(productId)}`, {
            method: 'POST',
            body: formData
          });

          state.currentSupplierProductId = null;
          notify(response.message || 'تم تحديث المنتج وإرساله للمراجعة.');
          go('supplier-products');
        } catch (error) {
          notify(error.message || 'تعذر تحديث المنتج.');
          button.disabled = false;
          button.textContent = 'حفظ التعديلات وإرسالها للمراجعة';
        }
      });
    } catch (error) {
      app.innerHTML = shell(
        'تعديل المنتج',
        'تعذر تحميل بيانات المنتج.',
        `<div class="panel">
          <p>${escapeAdminHtml(error.message)}</p>
          <button class="outline" data-page="supplier-products">العودة لمنتجاتي</button>
          <button class="primary" data-page="supplier-product-edit">إعادة المحاولة</button>
        </div>`
      );
    }
  }


  function supplierOrderStatusLabel(status) {
    const labels = {
      pending_payment: 'بانتظار العربون',
      pending_shipping_cost: 'بانتظار تحديد تكلفة الشحن',
      confirmed: 'تم تأكيد الطلب',
      processing: 'قيد التجهيز',
      shipped: 'تم الشحن',
      delivered: 'تم التسليم',
      cancelled: 'ملغي',
      completed: 'مكتمل'
    };

    return labels[status] || status || 'غير محدد';
  }

  function supplierPaymentStatusLabel(status) {
    const labels = {
      pending: 'لم يتم الدفع',
      pending_deposit: 'بانتظار العربون',
      deposit_pending_review: 'العربون قيد المراجعة',
      deposit_paid: 'تم دفع العربون',
      partially_paid: 'مدفوع جزئيًا',
      paid: 'مدفوع بالكامل',
      completed: 'مكتمل',
      refunded: 'تم رد المبلغ',
      failed: 'فشل الدفع'
    };

    return labels[status] || status || 'غير محدد';
  }

  function renderSupplierOrders() {
    if (
      !state.token ||
      !state.user ||
      state.user.role !== 'supplier' ||
      !state.supplierDashboard ||
      !state.supplierDashboard.supplier
    ) {
      app.innerHTML = shell(
        'طلبات المورد',
        'هذه الصفحة متاحة للموردين المعتمدين فقط.',
        '<div class="panel"><p>لا تتوفر صلاحية الدخول إلى طلبات المورد.</p><button class="outline" data-page="supplier-dashboard">العودة للوحة المورد</button></div>'
      );
      return;
    }

    app.innerHTML = shell(
      'طلبات المورد',
      'تابع الطلبات الواردة لنشاطك وتفاصيل كل طلب.',
      `
        <div class="panel admin-orders-toolbar">
          <button class="outline" type="button" data-supplier-orders-action="back">العودة للوحة المورد</button>
          <button class="outline" type="button" data-supplier-orders-action="refresh">تحديث الطلبات</button>
        </div>
        <div id="supplierOrdersList">
          <div class="panel empty">جارٍ تحميل الطلبات...</div>
        </div>
      `
    );

    loadSupplierOrders();
  }

  async function loadSupplierOrders() {
    const box = document.getElementById('supplierOrdersList');
    if (!box) return;

    box.innerHTML = '<div class="panel empty">جارٍ تحميل الطلبات...</div>';

    try {
      const result = await apiRequest('/supplier/orders');
      const orders = Array.isArray(result.orders) ? result.orders : [];

      state.supplierOrders = orders;

      if (!orders.length) {
        box.innerHTML = `
          <div class="panel empty">
            <h3>لا توجد طلبات حتى الآن</h3>
            <p>ستظهر هنا الطلبات التي تحتوي على منتجات تابعة لنشاطك.</p>
          </div>
        `;
        return;
      }

      box.innerHTML = orders.map(order => {
        const item = order.item || (Array.isArray(order.items) ? order.items[0] : null);
        const productName = item
          ? (
              item.product_name_ar ||
              item.product_name_en ||
              item.product_name ||
              item.name_ar ||
              item.name_en ||
              item.name ||
              item.product?.name_ar ||
              item.product?.name_en ||
              item.product?.name ||
              'تفاصيل المنتج داخل الطلب'
            )
          : 'طلب متعدد المنتجات';

        const typeLabel = order.type === 'individual'
          ? 'طلب فردي'
          : 'طلب متعدد الموردين';

        const dateLabel = order.created_at
          ? new Date(order.created_at).toLocaleString('ar-EG')
          : '—';

        return `
          <article class="panel">
            <div class="admin-orders-toolbar">
              <h3>طلب رقم ${escapeAdminHtml(order.order_number || order.id)}</h3>
              <span class="status">${escapeAdminHtml(supplierOrderStatusLabel(order.status))}</span>
            </div>
            <p><strong>نوع الطلب:</strong> ${typeLabel}</p>
            <p><strong>المنتج:</strong> ${escapeAdminHtml(productName)}</p>
            <p><strong>العميل:</strong> ${escapeAdminHtml(order.recipient_name || '—')}</p>
            <p><strong>حالة الدفع:</strong> ${escapeAdminHtml(supplierPaymentStatusLabel(order.payment_status))}</p>
            <p><strong>تاريخ الطلب:</strong> ${escapeAdminHtml(dateLabel)}</p>
            <p><strong>إجمالي طلبك:</strong> ${Number(order.grand_total || 0).toLocaleString('ar-EG')} ج.م</p>
            <button
              class="primary"
              type="button"
              data-supplier-order-id="${escapeAdminHtml(order.id)}"
              data-supplier-order-type="${escapeAdminHtml(order.type)}"
            >عرض التفاصيل</button>
          </article>
        `;
      }).join('');
    } catch (error) {
      box.innerHTML = `
        <div class="panel empty">
          <p>تعذر تحميل الطلبات: ${escapeAdminHtml(error.message)}</p>
          <button class="outline" type="button" data-supplier-orders-action="refresh">إعادة المحاولة</button>
        </div>
      `;
    }
  }


  async function openSupplierOrder(type, id, button = null) {
    if (!type || !id) return;

    const originalText = button ? button.textContent : null;
    if (button) {
      button.disabled = true;
      button.textContent = 'جارٍ تحميل التفاصيل...';
    }

    try {
      const result = await apiRequest(
        '/supplier/orders/' +
        encodeURIComponent(type) + '/' +
        encodeURIComponent(id)
      );

      if (!result || !result.order) {
        throw new Error('لم يتم العثور على تفاصيل الطلب');
      }

      state.currentSupplierOrder = {
        type: result.type || type,
        order: result.order
      };

      go('supplier-order-details');
    } catch (error) {
      notify(error.message || 'تعذر تحميل تفاصيل الطلب');
      if (button) {
        button.disabled = false;
        button.textContent = originalText || 'عرض التفاصيل';
      }
    }
  }

  function renderSupplierOrderDetails() {
    const current = state.currentSupplierOrder;

    if (
      !state.token ||
      !state.user ||
      state.user.role !== 'supplier' ||
      !state.supplierDashboard ||
      !state.supplierDashboard.supplier ||
      !current ||
      !current.order
    ) {
      app.innerHTML = shell(
        'تفاصيل الطلب',
        'لا تتوفر بيانات الطلب أو صلاحية الوصول.',
        '<div class="panel"><button class="outline" data-page="supplier-orders">العودة للطلبات</button></div>'
      );
      return;
    }

    const order = current.order;
    const type = current.type;
    const items = type === 'individual'
      ? (order.item ? [order.item] : [])
      : (Array.isArray(order.items) ? order.items : []);

    const money = value =>
      Number(value || 0).toLocaleString('ar-EG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }) + ' ج.م';

    const dateLabel = order.created_at
      ? new Date(order.created_at).toLocaleString('ar-EG')
      : '—';

    const addressParts = [
      order.governorate,
      order.city
    ].filter(Boolean);

    const addressLabel = addressParts.length
      ? addressParts.join(' - ')
      : '—';

    const typeLabel = type === 'individual'
      ? 'طلب فردي'
      : 'طلب متعدد الموردين';

    app.innerHTML = shell(
      'تفاصيل الطلب',
      'مراجعة بيانات الطلب والأصناف الخاصة بنشاطك.',
      `
        <div class="panel admin-orders-toolbar">
          <button class="outline" type="button" data-supplier-orders-action="back">العودة للطلبات</button>
          <button class="outline" type="button" data-supplier-orders-action="refresh-details">تحديث التفاصيل</button>
        </div>

        <section class="panel">
          <div class="admin-orders-toolbar">
            <h3>طلب رقم ${escapeAdminHtml(order.order_number || order.id)}</h3>
            <span class="status">${escapeAdminHtml(supplierOrderStatusLabel(order.status))}</span>
          </div>
          <p><strong>نوع الطلب:</strong> ${typeLabel}</p>
          <p><strong>حالة الدفع:</strong> ${escapeAdminHtml(supplierPaymentStatusLabel(order.payment_status))}</p>
          <p><strong>تاريخ الطلب:</strong> ${escapeAdminHtml(dateLabel)}</p>
        </section>

        <section class="panel">
          <h3>بيانات العميل والتوصيل</h3>
          <p><strong>اسم المستلم:</strong> ${escapeAdminHtml(order.recipient_name || '—')}</p>
          <p><strong>المحافظة والمدينة:</strong> ${escapeAdminHtml(addressLabel)}</p>
        </section>

        <section class="panel">
          <h3>أصناف طلبك</h3>
          ${
            items.length
              ? items.map(item => `
                  <article class="panel">
                    <h4>${escapeAdminHtml(item.product_name_ar || item.product_name_en || 'منتج')}</h4>
                    <p><strong>الوحدة:</strong> ${escapeAdminHtml(item.unit_name || '—')}</p>
                    <p><strong>سعر الوحدة:</strong> ${money(item.unit_price)}</p>
                    <p><strong>الكمية:</strong> ${escapeAdminHtml(item.quantity ?? '—')}</p>
                    <p><strong>إجمالي الصنف:</strong> ${money(item.line_total)}</p>
                  </article>
                `).join('')
              : '<p>لا توجد تفاصيل أصناف متاحة لهذا الطلب.</p>'
          }
        </section>

        <section class="panel">
          <h3>ملخص المبالغ</h3>
          <p><strong>قيمة المنتجات:</strong> ${money(order.subtotal)}</p>
          <p><strong>تكلفة الشحن:</strong> ${money(order.shipping_total)}</p>
          <p><strong>إجمالي الطلب:</strong> ${money(order.grand_total)}</p>
          <p><strong>قيمة العربون:</strong> ${money(order.deposit_amount)}</p>
          <p><strong>الرصيد المتبقي:</strong> ${money(order.balance_amount)}</p>
        </section>

        ${
          order.cancellation_reason
            ? `<section class="panel"><h3>سبب الإلغاء</h3><p>${escapeAdminHtml(order.cancellation_reason)}</p></section>`
            : ''
        }

        <div class="panel">
          <p>هذه التفاصيل للعرض والمتابعة فقط. تحديث حالة الطلب ومراجعة المدفوعات من اختصاص الإدارة.</p>
        </div>
      `
    );
  }

  function renderSupplierDashboard() {
    const data = state.supplierDashboard;

    if (!state.token || !data || !data.supplier) {
      app.innerHTML = shell(
        'لوحة المورد',
        'هذه الصفحة متاحة للموردين المعتمدين فقط.',
        '<div class="panel"><p>لا تتوفر صلاحية الدخول إلى لوحة المورد.</p><button class="outline" data-page="home">العودة للسوق</button></div>'
      );
      return;
    }

    const supplier = data.supplier;
    const products = data.products || {};
    const orders = data.orders || {};

    app.innerHTML = shell(
      'لوحة المورد',
      'إدارة نشاطك ومنتجاتك وطلباتك على GOZORX.',
      `
        <section class="panel">
          <h2>${escapeAdminHtml(supplier.business_name || 'نشاط المورد')}</h2>
          <p>${escapeAdminHtml(supplier.business_type || '')}</p>
          <p>${escapeAdminHtml(supplier.address || '')}</p>
          <span class="status">حالة الحساب: معتمد</span>
        </section>

        <div class="admin-order-grid">
          <article class="panel">
            <h3>إجمالي المنتجات</h3>
            <strong>${Number(products.total || 0)}</strong>
          </article>
          <article class="panel">
            <h3>المنتجات المنشورة</h3>
            <strong>${Number(products.published || 0)}</strong>
          </article>
          <article class="panel">
            <h3>منتجات المسودة</h3>
            <strong>${Number(products.draft || 0)}</strong>
          </article>
          <article class="panel">
            <h3>المنتجات المرفوضة</h3>
            <strong>${Number(products.rejected || 0)}</strong>
          </article>
          <article class="panel">
            <h3>إجمالي الطلبات</h3>
            <strong>${Number(orders.total || 0)}</strong>
          </article>
          <article class="panel">
            <h3>الطلبات النشطة</h3>
            <strong>${Number(orders.active || 0)}</strong>
          </article>
          <article class="panel">
            <h3>الطلبات المكتملة</h3>
            <strong>${Number(orders.completed || 0)}</strong>
          </article>
        </div>

        <section class="panel">
          <h3>إدارة المنتجات</h3>
          <button class="primary" data-page="supplier-products" type="button">عرض منتجاتي</button>
          <button class="primary" data-page="supplier-add-product" type="button">إضافة منتج جديد</button>
        </section>

        <section class="panel">
          <h3>إدارة الطلبات</h3>
          <p>تابع الطلبات الواردة من العملاء وتفاصيل المنتجات والتسليم.</p>
          <button class="primary" data-page="supplier-orders" type="button">عرض الطلبات</button>
        </section>

        <section class="panel">
          <button class="outline" data-page="supplier-application" type="button">بيانات طلب المورد</button>
          <button class="outline" data-page="home" type="button">العودة للسوق</button>
        </section>
      `
    );
  }


  async function renderSupplierProducts() {
    if (!state.token || !state.user || state.user.role === 'admin') {
      app.innerHTML = shell(
        'منتجاتي',
        'هذه الصفحة متاحة للموردين المعتمدين فقط.',
        '<div class="panel"><button class="primary" data-page="login">تسجيل الدخول</button></div>'
      );
      return;
    }

    app.innerHTML = shell(
      'منتجاتي',
      'جارٍ تحميل منتجات نشاطك...',
      '<div class="panel empty">يرجى الانتظار...</div>'
    );

    try {
      const result = await apiRequest('/supplier/products');
      const products = Array.isArray(result.data) ? result.data : [];

      const statusLabels = {
        published: 'منشور في السوق',
        draft: 'مسودة — بانتظار المراجعة',
        rejected: 'مرفوض'
      };

      const content = products.length
        ? products.map(product => `
          <article class="panel admin-order-card">
            <div class="admin-order-heading">
              <h3>${escapeAdminHtml(product.name_ar || product.name_en || 'منتج')}</h3>
              <span class="status">${escapeAdminHtml(statusLabels[product.status] || product.status || 'غير محدد')}</span>
            </div>
            <div class="admin-order-grid">
              <div><span>رقم المنتج</span><strong>${escapeAdminHtml(product.id)}</strong></div>
              <div><span>السعر</span><strong>${escapeAdminHtml(product.unit_price)} جنيه</strong></div>
              <div><span>وحدة البيع</span><strong>${escapeAdminHtml(product.unit_name || '—')}</strong></div>
              <div><span>الحد الأدنى للطلب</span><strong>${escapeAdminHtml(product.minimum_order_quantity ?? '—')}</strong></div>
              <div><span>الكمية المتاحة</span><strong>${escapeAdminHtml(product.available_quantity ?? '—')}</strong></div>
              <div><span>القسم</span><strong>${escapeAdminHtml(product.category?.name_ar || '—')}</strong></div>
            </div>
            ${product.description ? `<p>${escapeAdminHtml(product.description)}</p>` : ''}
            <div class="admin-product-actions">
              <button class="primary" type="button"
                data-supplier-product-edit="${escapeAdminHtml(product.id)}">تعديل المنتج</button>
            </div>
          </article>
        `).join('')
        : '<div class="panel empty">لم تضف أي منتجات حتى الآن.</div>';

      app.innerHTML = shell(
        'منتجاتي',
        'تابع منتجاتك وحالات مراجعتها، ويمكنك تعديل أي منتج.',
        `
          <div class="admin-orders-toolbar panel">
            <button class="outline" type="button" data-supplier-products-action="refresh">تحديث القائمة</button>
            <button class="primary" type="button" data-page="supplier-add-product">إضافة منتج جديد</button>
          </div>
          <div id="supplierProductsList">${content}</div>
          <section class="panel">
            <button class="outline" type="button" data-page="supplier-dashboard">العودة إلى لوحة المورد</button>
          </section>
        `
      );
    } catch (error) {
      app.innerHTML = shell(
        'منتجاتي',
        'تعذر تحميل قائمة المنتجات.',
        `<div class="panel">
          <p>${escapeAdminHtml(error.message)}</p>
          <button class="outline" type="button" data-supplier-products-action="refresh">إعادة المحاولة</button>
          <button class="outline" type="button" data-page="supplier-dashboard">لوحة المورد</button>
        </div>`
      );
    }
  }

  function renderHome(){return homeMarkup;}
  function render(){
    if(state.page==='home'){app.innerHTML=renderHome();return;}

    if(state.page==='supplier-dashboard'){
      renderSupplierDashboard();
      return;
    }

    if(state.page==='supplier-products'){
      renderSupplierProducts();
      return;
    }

    if(state.page==='supplier-orders'){
      renderSupplierOrders();
      return;
    }

    if(state.page==='supplier-order-details'){
      renderSupplierOrderDetails();
      return;
    }

    if(state.page==='supplier-application'){
      renderSupplierApplication();
      return;
    }

    if(state.page==='supplier-add-product'){
      renderSupplierAddProduct();
      return;
    }

    if(state.page==='supplier-product-edit'){
      renderSupplierProductEdit();
      return;
    }

    if(state.page==='admin-products'){
      renderAdminProducts();
      return;
    }

    if(state.page==='admin-suppliers'){
      renderAdminSuppliers();
      return;
    }

    if(state.page==='admin-supplier-details'){
      renderAdminSupplierDetails();
      return;
    }

    if(state.page==='admin-payments'){
      renderAdminPayments();
      return;
    }

    if(state.page==='admin-orders'){
      renderAdminOrders();
      return;
    }

    if(state.page==='admin-order-details'){
      renderAdminOrderDetails();
      return;
    }

    if(state.page==='my-orders'){
      renderMyOrders();
      return;
    }

    if(state.page==='order-details'){
      const order = state.currentOrder;

      if(!order){
        app.innerHTML = shell(
          'تفاصيل الطلب',
          'لا توجد بيانات للطلب.',
          '<div class="panel"><button class="outline" data-page="my-orders">العودة إلى طلباتي</button></div>'
        );
        return;
      }

      const payment = order.deposit_transaction || null;
      const labels = {
        pending_payment: 'بانتظار العربون',
        pending_shipping_cost: 'بانتظار تحديد تكلفة الشحن',
        confirmed: 'تم تأكيد الطلب',
        processing: 'قيد التجهيز',
        shipped: 'تم الشحن',
        delivered: 'تم التسليم',
        cancelled: 'ملغي',
        completed: 'مكتمل'
      };

      const receiptStatus = !order.receipt_uploaded
        ? 'لم يتم رفع الإيصال'
        : payment?.status === 'rejected'
          ? 'الإيصال مرفوض'
          : payment?.status === 'approved'
            ? 'تم اعتماد العربون'
            : 'بانتظار مراجعة الإدارة';

            const shippingPending = order.status === 'pending_shipping_cost';

      const totalDisplay = shippingPending
        ? 'بانتظار تحديد الشحن'
        : `${Number(order.grand_total || 0).toLocaleString('ar-EG')} جنيه`;

      const depositDisplay = shippingPending
        ? 'يُحدد بعد الشحن'
        : `${Number(order.deposit_amount || 0).toLocaleString('ar-EG')} جنيه`;

      const balanceDisplay = shippingPending
        ? 'يُحدد بعد الشحن'
        : `${Number(order.balance_amount || 0).toLocaleString('ar-EG')} جنيه`;
            const reviewNoteTitle = payment?.status === 'pending'
  ? 'ملاحظة بخصوص الإيصال السابق:'
  : 'سبب الرفض أو ملاحظة الإدارة:';

const reviewNote = payment?.review_note
  ? `<div class="payment-notice">
      <strong>${reviewNoteTitle}</strong>
      <p>${escapeAdminHtml(payment.review_note)}</p>
    </div>`
  : '';

 const individualItem = order.individual_item || order.individualItem || null;
const individualSupplier = order.supplier || {};

const individualSection = individualItem
  ? `
    <section class="panel admin-order-supplier">
      <h3>تفاصيل المنتج</h3>
      <p><strong>اسم المورد:</strong> ${escapeAdminHtml(
        individualSupplier.business_name || 'مورد'
      )}</p>
      <p><strong>المنتج:</strong> ${escapeAdminHtml(
        individualItem.product_name_ar ||
        individualItem.product_name_en ||
        'منتج'
      )}</p>
      <p><strong>الكمية:</strong> ${Number(individualItem.quantity || 0)}
        ${escapeAdminHtml(individualItem.unit_name || 'وحدة')}</p>
      <p><strong>سعر الوحدة:</strong> ${Number(
        individualItem.unit_price || 0
      ).toLocaleString('ar-EG')} جنيه</p>
      <p><strong>إجمالي المنتج:</strong> ${Number(
        individualItem.line_total || 0
      ).toLocaleString('ar-EG')} جنيه</p>
    </section>
  `
  : '';

const supplierOrders = Array.isArray(order.supplier_orders)
  ? order.supplier_orders
  : (Array.isArray(order.supplierOrders) ? order.supplierOrders : []);

const legacySupplierSections = supplierOrders.length
  ? supplierOrders.map((supplierOrder, index) => {
      const supplier = supplierOrder.supplier || {};
      const supplierUser = supplier.user || {};
      const items = Array.isArray(supplierOrder.items) ? supplierOrder.items : [];
      const supplierName = supplier.business_name ||
        supplierUser.full_name || supplierUser.name || 'مورد';

      return `
        <section class="panel admin-order-supplier">
          <h3>طلب المورد ${index + 1}</h3>
          <p><strong>اسم المورد:</strong> ${escapeAdminHtml(supplierName)}</p>
          <p><strong>حالة طلب المورد:</strong> ${escapeAdminHtml(
            labels[supplierOrder.status] || supplierOrder.status || 'غير محدد'
          )}</p>
          <p><strong>إجمالي طلب المورد:</strong> ${Number(
            supplierOrder.grand_total || supplierOrder.subtotal || 0
          ).toLocaleString('ar-EG')} جنيه</p>
          ${
            items.length
              ? `<ul>${items.map(item => `
                  <li>
                    <strong>${escapeAdminHtml(
                      item.product_name_ar || item.product_name_en || item.product_name || 'منتج'
                    )}</strong>
                    — الكمية: ${Number(item.quantity || 0)}
                    ${escapeAdminHtml(item.unit_name || 'وحدة')}
                    — سعر الوحدة: ${Number(item.unit_price || 0).toLocaleString('ar-EG')} جنيه
                    — الإجمالي: ${Number(item.line_total || 0).toLocaleString('ar-EG')} جنيه
                  </li>
                `).join('')}</ul>`
              : '<p>لا توجد أصناف مسجلة لهذا المورد.</p>'
          }
        </section>
      `;
    }).join('')
  : '';

const supplierSections = individualSection || legacySupplierSections ||
  '<div class="panel empty">لا توجد تفاصيل أصناف متاحة لهذا الطلب حاليًا.</div>';
      app.innerHTML = shell(
        'تفاصيل الطلب',
        'رقم الطلب: ' + escapeAdminHtml(order.order_number || order.id),
        `
          <div class="panel order-payment-summary">
            <h3>ملخص الطلب</h3>
            <p>حالة الطلب: <strong>${escapeAdminHtml(labels[order.status] || order.status || 'غير محدد')}</strong></p>
            <p>إجمالي الطلب: <strong>${escapeAdminHtml(totalDisplay)}</strong></p>
            <p>العربون (30%): <strong>${escapeAdminHtml(depositDisplay)}</strong></p>
            <p>المتبقي عند الاستلام (70%): <strong>${escapeAdminHtml(balanceDisplay)}</strong></p>
            <p>حالة الإيصال: <strong>${receiptStatus}</strong></p>
          </div>
          ${reviewNote}
          <h3>تفاصيل الموردين والمنتجات</h3>
          ${supplierSections}
          <div class="my-order-actions">
            ${order.status === 'pending_payment' ? '<button class="primary" data-open-order-payment>متابعة العربون</button>' : ''}
            <button class="outline" data-page="my-orders">العودة إلى طلباتي</button>
          </div>
        `
      );
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
            <div class="product-img">${p.image_path
              ? `<img src="${safe(API_BASE.replace(/\/api\/v1\/?$/, '') + '/storage/' + String(p.image_path).split('/').map(encodeURIComponent).join('/'))}" alt="${safe(p.name_ar || p.name_en || 'منتج زراعي')}" loading="lazy">`
              : '🌱'}</div>
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

    if(state.page==='checkout'){
      if(!state.cart.length){
        app.innerHTML = shell(
          'إتمام الطلب',
          'سلة المشتريات فارغة.',
          '<div class="panel empty">أضف منتجات إلى السلة أولًا.</div><button class="primary" data-page="market">تصفح السوق</button>'
        );
        return;
      }

      if(!state.token || !state.user){
        state.returnToCheckout = true;
        app.innerHTML = shell(
          'تسجيل الدخول مطلوب',
          'سجّل الدخول لمتابعة إتمام طلبك.',
          '<div class="panel"><button class="primary" data-page="login">تسجيل الدخول</button></div>'
        );
        return;
      }

      const total = state.cart.reduce(
        (sum, item) => sum + Number(item.product.unit_price || 0) * item.quantity,
        0
      );

      const safeCheckout = value => escapeAdminHtml(value);
      const user = state.user || {};

      const summary = state.cart.map(item => `
        <div class="checkout-summary-item">
          <span>${safeCheckout(item.product.name_ar || item.product.name_en || 'منتج زراعي')} × ${item.quantity}</span>
          <strong>${(Number(item.product.unit_price || 0) * item.quantity).toLocaleString('ar-EG')} جنيه</strong>
        </div>
      `).join('');

      app.innerHTML = shell(
        'بيانات الشحن وإتمام الطلب',
        'أدخل بيانات استلام الطلب واختر وسيلة تحويل العربون.',
        `
        <div class="checkout-layout">
          <form class="panel form-grid" id="checkoutForm">
            <h3 class="field full">بيانات مستلم الطلب</h3>

            ${field('اسم المستلم','recipient_name')}
            ${field('رقم هاتف المستلم','recipient_phone','tel')}

            ${field('المحافظة','governorate')}
            ${field('المدينة / المركز','city')}

            <div class="field full">
              <label>العنوان بالتفصيل</label>
              <textarea name="address" required maxlength="1000"></textarea>
            </div>

            <div class="field full">
              <label>ملاحظات التوصيل (اختياري)</label>
              <textarea name="delivery_notes" maxlength="2000"></textarea>
            </div>

            <h3 class="field full">وسيلة تحويل العربون</h3>

            <div class="field full">
              <label for="payment_method">اختر وسيلة الدفع</label>
              <select name="payment_method" id="payment_method" required>
                <option value="vodafone_cash">فودافون كاش</option>
                <option value="instapay">InstaPay</option>
              </select>
            </div>

            <div class="field full">
              <div class="payment-notice">
                سيتم دفع 30% من إجمالي الطلب مقدمًا بعد التحويل ومراجعة الإيصال.
                المتبقي 70% يُدفع عند الاستلام.
              </div>
            </div>

            <div class="field full">
              <button class="primary" type="submit" id="placeOrderBtn">تأكيد بيانات الطلب</button>
              <button class="outline" type="button" data-page="cart">العودة إلى السلة</button>
            </div>
          </form>

          <aside class="panel checkout-summary">
            <h3>ملخص مشترياتك</h3>
            ${summary}
            <div class="checkout-summary-total">
              <span>إجمالي المنتجات</span>
              <strong>${total.toLocaleString('ar-EG')} جنيه</strong>
            </div>
            <p>العربون المطلوب (30%): <strong>${(total * 0.30).toLocaleString('ar-EG',{maximumFractionDigits:2})} جنيه</strong></p>
            <p>المتبقي عند الاستلام (70%): <strong>${(total * 0.70).toLocaleString('ar-EG',{maximumFractionDigits:2})} جنيه</strong></p>
            <small class="hint">سيتم تثبيت الكميات لمدة 24 ساعة بعد إنشاء الطلب.</small>
          </aside>
        </div>
        `
      );

      const checkoutForm = document.getElementById('checkoutForm');
      const checkoutButton = document.getElementById('placeOrderBtn');

      if(user.full_name) checkoutForm.elements.recipient_name.value = user.full_name;
      else if(user.name) checkoutForm.elements.recipient_name.value = user.name;

      if(user.phone) checkoutForm.elements.recipient_phone.value = user.phone;
      if(user.governorate) checkoutForm.elements.governorate.value = user.governorate;
      if(user.city) checkoutForm.elements.city.value = user.city;

      checkoutForm.addEventListener('submit', async e => {
        e.preventDefault();

        const formData = Object.fromEntries(new FormData(checkoutForm));
        const payload = {
          ...formData,
          items: state.cart.map(item => ({
            product_id: Number(item.product.id),
            quantity: Number(item.quantity)
          }))
        };

        checkoutButton.disabled = true;
        checkoutButton.textContent = 'جارٍ إنشاء الطلب...';

        try {
          const result = await apiRequest('/orders', {
            method: 'POST',
            body: JSON.stringify(payload)
          });

          if (!Array.isArray(result.orders) || result.orders.length === 0) {
  throw new Error('تم الاتصال بالخادم لكن لم يتم استلام بيانات الطلبات.');
}

state.currentOrder = null;
localStorage.removeItem('gozorx_current_order_id');

state.cart = [];
updateCartCount();

notify('تم إنشاء طلباتك بنجاح. يمكنك متابعة حالتها من صفحة طلباتي.');
go('my-orders');
        } catch(error) {
          notify(error.message);
          checkoutButton.disabled = false;
          checkoutButton.textContent = 'تأكيد بيانات الطلب';
        }
      });

      return;
    }

    if(state.page==='order-payment'){
      const order = state.currentOrder;

      if(!order){
        app.innerHTML = shell(
          'ملخص الطلب',
          'لا يوجد طلب حالي في هذه الجلسة.',
          '<div class="panel empty">أنشئ طلبًا من السلة أولًا.</div><button class="primary" data-page="market">العودة إلى السوق</button>'
        );
        return;
      }

      const expiry = order.deposit_expires_at
        ? new Date(order.deposit_expires_at).toLocaleString('ar-EG')
        : 'خلال 24 ساعة من إنشاء الطلب';

      const paymentMethod = order.payment_method === 'instapay'
        ? 'InstaPay'
        : 'فودافون كاش';

      const paymentAccount = order.payment_method === 'instapay'
        ? '01149313719'
        : '01065014560';

      const depositAmount = Number(order.deposit_amount || 0).toLocaleString('ar-EG');
      const accountHolder = 'أحمد السيد (Ahmed El Sayed)';
      const paymentDetailsText = `GOZORX - ${paymentMethod}\nرقم التحويل: ${paymentAccount}\nاسم صاحب الحساب: ${accountHolder}\nمبلغ العربون: ${depositAmount} جنيه`;

      const paymentDetails = `
        <div class="panel payment-account-card">
          <h3>بيانات التحويل المعتمدة</h3>
          <p>حوّل مبلغ العربون الموضح أدناه إلى الحساب الخاص بوسيلة الدفع المختارة.</p>
          <div class="payment-account-row"><span>وسيلة الدفع</span><strong>${paymentMethod}</strong></div>
          <div class="payment-account-row"><span>رقم التحويل</span><strong dir="ltr">${paymentAccount}</strong></div>
          <div class="payment-account-row"><span>اسم صاحب الحساب</span><strong>${accountHolder}</strong></div>
          <div class="payment-account-row"><span>مبلغ العربون المطلوب</span><strong>${depositAmount} جنيه</strong></div>
          <button class="outline" type="button" id="copyPaymentDetailsBtn">نسخ بيانات التحويل</button>
        </div>
      `;

      const payment = order.deposit_transaction || null;
      const paymentStatus = payment?.status ||
        (order.receipt_uploaded ? 'pending' : null);

      const rejectedNotice = paymentStatus === 'rejected'
        ? `<div class="payment-notice">
            <strong>تم رفض إيصال التحويل السابق.</strong>
            <p>سبب الرفض: ${escapeAdminHtml(payment?.review_note || 'يرجى مراجعة الإدارة.')}</p>
            <p>يمكنك رفع إيصال جديد خلال المهلة المتبقية.</p>
          </div>`
        : '';

      let receiptSection = '';

      if (order.status !== 'pending_payment') {
        receiptSection = paymentStatus === 'approved'
          ? '<div class="payment-notice"><strong>تم اعتماد العربون وتأكيد الطلب.</strong></div>'
          : '<div class="payment-notice">الطلب لم يعد في حالة انتظار العربون، ولا يمكن رفع إيصال جديد.</div>';
      } else if (paymentStatus === 'approved') {
        receiptSection = '<div class="payment-notice"><strong>تم اعتماد العربون وتأكيد الطلب.</strong><p>تم قبول الإيصال من الإدارة.</p></div>';
      } else if (paymentStatus === 'pending' && order.receipt_uploaded) {
        receiptSection = '<div class="payment-notice"><strong>تم استلام إيصال التحويل.</strong><p>الإيصال بانتظار مراجعة الإدارة. الطلب لم يتأكد بعد.</p></div>';
      } else {
        receiptSection = paymentDetails + rejectedNotice + `
          <form class="panel form-grid" id="depositReceiptForm">
            <h3 class="field full">${paymentStatus === 'rejected' ? 'رفع إيصال بديل' : 'رفع إيصال العربون بعد التحويل'}</h3>
            <div class="field full">
              <label>رقم العملية (اختياري)</label>
              <input name="transaction_reference" maxlength="160"
                value="${escapeAdminHtml(payment?.transaction_reference || '')}">
            </div>
            <div class="field full">
              <label>صورة أو ملف الإيصال</label>
              <input name="receipt" type="file" accept=".jpg,.jpeg,.png,.pdf" required>
              <small class="hint">الصيغ المسموحة JPG وPNG وPDF، بحد أقصى 5 ميجابايت.</small>
            </div>
            <div class="field full">
              <button class="primary" type="submit" id="uploadReceiptBtn">رفع إيصال التحويل</button>
            </div>
          </form>
        `;
      }

      app.innerHTML = shell(
        'تفاصيل الطلب والعربون',
        'احتفظ برقم الطلب وتابع حالة مراجعة العربون.',
        `
        <div class="panel order-payment-summary">
          <h3>طلب رقم: ${escapeAdminHtml(order.order_number || order.id)}</h3>
          <p>وسيلة التحويل المختارة: <strong>${paymentMethod}</strong></p>
          <p>إجمالي الطلب: <strong>${Number(order.grand_total || 0).toLocaleString('ar-EG')} جنيه</strong></p>
          <p>العربون المطلوب (30%): <strong>${Number(order.deposit_amount || 0).toLocaleString('ar-EG')} جنيه</strong></p>
          <p>المتبقي عند الاستلام (70%): <strong>${Number(order.balance_amount || 0).toLocaleString('ar-EG')} جنيه</strong></p>
          <p>آخر موعد لمراجعة العربون: <strong>${expiry}</strong></p>
        </div>
        ${receiptSection}
        ${order.status === 'pending_payment' ? `
          <div class="payment-notice">
            رفع الإيصال لا يعني تأكيد الدفع. ستظل الكميات محجوزة لمدة 24 ساعة من إنشاء الطلب، ويصبح الطلب مؤكدًا بعد اعتماد الإدارة للعربون.
          </div>
        ` : ''}
        <div class="my-order-actions">
          <button class="outline" data-page="order-details">العودة إلى تفاصيل الطلب</button>
          <button class="outline" data-page="my-orders">طلباتي</button>
        </div>
        `
      );

      const copyPaymentDetailsBtn = document.getElementById('copyPaymentDetailsBtn');

      if(copyPaymentDetailsBtn){
        copyPaymentDetailsBtn.addEventListener('click', async () => {
          try {
            await navigator.clipboard.writeText(paymentDetailsText);
            notify('تم نسخ بيانات التحويل بنجاح');
          } catch(error) {
            notify('تعذر النسخ تلقائيًا. يمكنك نسخ الرقم يدويًا.');
          }
        });
      }

      const receiptForm = document.getElementById('depositReceiptForm');

      if(receiptForm){
        receiptForm.addEventListener('submit', async e => {
          e.preventDefault();

          const uploadButton = document.getElementById('uploadReceiptBtn');
          const formData = new FormData(receiptForm);

          uploadButton.disabled = true;
          uploadButton.textContent = 'جارٍ رفع الإيصال...';

          try {
            const uploadResult = await apiRequest(
              `/orders/${encodeURIComponent(order.id)}/deposit-receipt`,
              {
                method: 'POST',
                body: formData
              }
            );

            try {
              const refreshed = await apiRequest(
                `/orders/${encodeURIComponent(order.id)}`
              );
              state.currentOrder = refreshed.order;
            } catch (refreshError) {
              console.warn('Order refresh after receipt upload failed:', refreshError);
              state.currentOrder = {
                ...state.currentOrder,
                receipt_uploaded: true,
                deposit_transaction: {
                  ...(state.currentOrder.deposit_transaction || {}),
                  status: uploadResult.payment?.status || 'pending',
                  transaction_reference:
                    uploadResult.payment?.transaction_reference || null,
                  receipt_uploaded: true
                }
              };
            }

            notify('تم استلام الإيصال وهو بانتظار مراجعة الإدارة');
            go('order-payment');
          } catch(error) {
            notify(error.message);
            uploadButton.disabled = false;
            uploadButton.textContent = 'رفع إيصال التحويل';
          }
        });
      }

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
            <small class="hint">تأكيد الطلب يتطلب دفع عربون 30% ومراجعته من إدارة GOZORX. المتبقي 70% عند الاستلام.</small>
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

      const storageBase = API_BASE.replace(/\/api\/v1\/?$/, '');
      const detailImages = Array.isArray(p.images)
        ? p.images.map(image => ({
            url: image.image_path
              ? storageBase + '/storage/' + String(image.image_path).split('/').map(encodeURIComponent).join('/')
              : (image.image_url
                  ? (/^https?:\/\//i.test(image.image_url)
                      ? image.image_url
                      : storageBase + (image.image_url.startsWith('/') ? '' : '/') + image.image_url)
                  : '')
          })).filter(image => image.url)
        : [];

      if (!detailImages.length && p.image_path) {
        detailImages.push({
          url: storageBase + '/storage/' + String(p.image_path).split('/').map(encodeURIComponent).join('/')
        });
      }

      app.innerHTML = shell(
        'تفاصيل المنتج',
        'تعرف على مواصفات المنتج ومعلومات المورد.',
        `<button class="textbtn detail-back" data-page="market">← العودة إلى السوق</button>
        <div class="product-detail">
          <div class="product-detail-gallery">
            <div class="product-detail-image" id="productDetailMainImage">
              ${detailImages.length
                ? `<img src="${safeDetail(detailImages[0].url)}" alt="${safeDetail(p.name_ar || p.name_en || 'منتج زراعي')}" loading="lazy">`
                : '🌱'}
            </div>
            ${detailImages.length > 1
              ? `<div class="product-detail-arrows">
                  <button type="button" data-detail-image-prev aria-label="الصورة السابقة">❮</button>
                  <button type="button" data-detail-image-next aria-label="الصورة التالية">❯</button>
                </div>`
              : ''}
            ${detailImages.length > 1
              ? `<div class="product-detail-thumbnails">
                  ${detailImages.map((image, index) =>
                    `<button type="button" class="product-detail-thumb ${index === 0 ? 'active' : ''}" data-detail-image-index="${index}" aria-label="عرض الصورة ${index + 1}">
                      <img src="${safeDetail(image.url)}" alt="صورة ${index + 1}" loading="lazy">
                    </button>`
                  ).join('')}
                </div>`
              : ''}
          </div>
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

      let currentDetailImageIndex = 0;

      const showDetailImage = index => {
        if (!detailImages.length) return;

        currentDetailImageIndex =
          (index + detailImages.length) % detailImages.length;

        const selectedImage = detailImages[currentDetailImageIndex];
        const mainImage = document.querySelector('#productDetailMainImage img');

        if (mainImage) {
          mainImage.src = selectedImage.url;
          mainImage.alt = `صورة ${currentDetailImageIndex + 1} من المنتج`;
        }

        document.querySelectorAll('.product-detail-thumb').forEach((thumb, thumbIndex) => {
          thumb.classList.toggle('active', thumbIndex === currentDetailImageIndex);
        });
      };

      document.querySelectorAll('[data-detail-image-index]').forEach(button => {
        button.addEventListener('click', () => {
          showDetailImage(Number(button.dataset.detailImageIndex));
        });
      });

      document.querySelector('[data-detail-image-prev]')?.addEventListener('click', () => {
        showDetailImage(currentDetailImageIndex - 1);
      });

      document.querySelector('[data-detail-image-next]')?.addEventListener('click', () => {
        showDetailImage(currentDetailImageIndex + 1);
      });

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
      const loggedIn = Boolean(state.token && state.user);

      if(loggedIn){
        app.innerHTML = shell(
          'إنشاء حساب مورد مستقل',
          'حساب المشتري وحساب المورد منفصلان تمامًا. لتقديم طلب مورد، سجّل الخروج أولًا ثم افتح صفحة انضمام المورد لإنشاء حساب مستقل.',
          '<div class="panel"><p>لن يتم تحويل حسابك الحالي أو تغيير دوره.</p><button class="primary" data-page="home">العودة إلى السوق</button></div>'
        );
        return;
      }

      const accountFields =
        `<div class="field full"><strong>إنشاء حساب المورد</strong><p class="hint">أدخل بياناتك لإنشاء حساب مورد مستقل وتقديم طلبك في نفس الخطوة.</p></div>
         <div class="field"><label>الاسم بالكامل</label><input name="full_name" required maxlength="160"></div>
         <div class="field"><label>رقم الهاتف</label><input name="phone" type="tel" required maxlength="30"></div>
         <div class="field"><label>البريد الإلكتروني</label><input name="email" type="email" required maxlength="255"></div>
         <div class="field"><label>كلمة المرور (10 أحرف على الأقل)</label><input name="password" type="password" required minlength="10"></div>
         <div class="field"><label>تأكيد كلمة المرور</label><input name="password_confirmation" type="password" required minlength="10"></div>`;

      app.innerHTML=shell(
        'طلب انضمام مورد',
        'أكمل بيانات نشاطك وارفع المستندات الأربعة. لن تتمكن من نشر المنتجات قبل مراجعة الإدارة والموافقة.',
        `<form class="panel form-grid" id="supplierForm" enctype="multipart/form-data">
          ${accountFields}

          <div class="field">
            <label>اسم المشتل / الشركة</label>
            <input name="business_name" required maxlength="200">
          </div>

          <div class="field">
            <label>نوع النشاط</label>
            <input name="business_type" maxlength="100">
          </div>

          <div class="field full">
            <label>العنوان بالتفصيل</label>
            <textarea name="address" required maxlength="2000"></textarea>
          </div>

          <input type="hidden" name="latitude" required>
          <input type="hidden" name="longitude" required>

          <div class="field full">
            <button class="secondary" type="button" id="supplierUseLocation">تحديد موقعي الحالي</button>
            <small class="hint" id="supplierLocationStatus">اضغط على الزر للسماح بتحديد موقعك.</small>
          </div>

          <div class="field full">
            <label>نبذة عن النشاط والمنتجات</label>
            <textarea name="description" maxlength="5000"></textarea>
          </div>

          <div class="field full">
            <strong>المستندات المطلوبة</strong>
            <small class="hint">الملفات PDF أو صور حسب نوع المستند، والحد الأقصى 10 ميجابايت لكل ملف.</small>
          </div>

          <div class="field">
            <label>السجل التجاري</label>
            <input type="file" name="commercial_register" accept=".pdf,.jpg,.jpeg,.png" required>
          </div>

          <div class="field">
            <label>البطاقة الضريبية</label>
            <input type="file" name="tax_card" accept=".pdf,.jpg,.jpeg,.png" required>
          </div>

          <div class="field">
            <label>الصورة الشخصية</label>
            <input type="file" name="personal_photo" accept=".jpg,.jpeg,.png" required>
          </div>

          <div class="field">
            <label>صورة بطاقة الرقم القومي</label>
            <input type="file" name="national_id" accept=".jpg,.jpeg,.png" required>
          </div>

          <div class="field full">
            <button class="primary" type="submit">إنشاء الحساب وإرسال طلب المورد</button>
            <small class="hint">سيظل طلبك قيد المراجعة حتى اعتماد المستندات الأربعة من الإدارة.</small>
          </div>
        </form>`
      );

      document.getElementById('supplierUseLocation').addEventListener('click', () => {
        if(!navigator.geolocation){
          notify('المتصفح لا يدعم تحديد الموقع.');
          return;
        }

        const form = document.getElementById('supplierForm');
        const locationButton = document.getElementById('supplierUseLocation');
        const locationStatus = document.getElementById('supplierLocationStatus');

        locationButton.disabled = true;
        locationButton.textContent = 'جارٍ تحديد الموقع...';
        locationStatus.textContent = 'يرجى السماح للمتصفح بالوصول إلى موقعك.';

        navigator.geolocation.getCurrentPosition(
          position => {
            form.elements.latitude.value = position.coords.latitude;
            form.elements.longitude.value = position.coords.longitude;
            locationButton.disabled = false;
            locationButton.textContent = 'تم تحديد الموقع — اضغط لإعادة التحديد';
            locationStatus.textContent = 'تم تحديد موقعك بنجاح.';
            notify('تم تحديد موقعك بنجاح.');
          },
          () => {
            locationButton.disabled = false;
            locationButton.textContent = 'تحديد موقعي الحالي';
            locationStatus.textContent = 'تعذر تحديد الموقع. تحقق من إذن الموقع وحاول مرة أخرى.';
            notify('تعذر تحديد الموقع. تأكد من السماح للمتصفح باستخدام الموقع.');
          },
          { enableHighAccuracy: false, timeout: 30000, maximumAge: 60000 }
        );
      });

      document.getElementById('supplierForm').addEventListener('submit', async e => {
        e.preventDefault();

        const form = e.target;
        const button = form.querySelector('button[type="submit"]');

        if(!form.elements.latitude.value || !form.elements.longitude.value){
          notify('يرجى تحديد موقعك باستخدام الزر قبل إرسال الطلب.');
          return;
        }

        if(!loggedIn && form.elements.password.value !== form.elements.password_confirmation.value){
          notify('كلمتا المرور غير متطابقتين.');
          return;
        }

        const formData = new FormData(form);
        button.disabled = true;
        button.textContent = 'جارٍ إرسال الطلب والمستندات...';

        try {
          const endpoint = '/supplier/register';
          const result = await apiRequest(endpoint, {
            method: 'POST',
            body: formData
          });

          saveSession(result);
          updateAccountArea();

          notify(result.message || 'تم إرسال طلب المورد بنجاح.');
          go('home');
        } catch(error) {
          notify(error.message || 'تعذر إرسال الطلب.');
          button.disabled = false;
          button.textContent = 'إنشاء الحساب وإرسال طلب المورد';
        }
      });

      return;
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
          const shouldReturnToCheckout = state.returnToCheckout;
          state.returnToCheckout = false;
if (shouldReturnToCheckout) {
  go('checkout');
} else {
  await restoreCurrentOrder(false);
  go('home');
}
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
          const shouldReturnToCheckout = state.returnToCheckout;
          state.returnToCheckout = false;

          if (shouldReturnToCheckout) {
            go('checkout');
          } else if (!(await restoreCurrentOrder(true))) {
            go('home');
          }
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
    const supplierApplicationAction = e.target.closest('[data-supplier-application-action]');
    if (supplierApplicationAction) {
      await renderSupplierApplication();
      return;
    }

    const refreshOrders = e.target.closest('[data-my-orders-action]');
    if (refreshOrders) {
      await loadMyOrders();
      return;
    }

    const myOrderButton = e.target.closest('[data-my-order-id]');
    if (myOrderButton) {
      await openMyOrder(myOrderButton.dataset.myOrderId, myOrderButton);
      return;
    }

    if (e.target.closest('[data-open-order-payment]')) {
      if (state.currentOrder) go('order-payment');
      return;
    }

    const adminProductsAction = e.target.closest('[data-admin-products-action]');
    if (adminProductsAction) {
      await loadAdminProducts();
      return;
    }

    const adminProductButton = e.target.closest('[data-admin-product-action]');
    if (adminProductButton) {
      await handleAdminProductDecision(
        adminProductButton.dataset.adminProductAction,
        adminProductButton.dataset.productId,
        adminProductButton
      );
      return;
    }

    const supplierAction = e.target.closest('[data-admin-suppliers-action]');
    if (supplierAction) {
      const action = supplierAction.dataset.adminSuppliersAction;
      if (action === 'back') {
        state.currentAdminSupplier = null;
        go('admin-suppliers');
      } else {
        await loadAdminSuppliers();
      }
      return;
    }

    const supplierButton = e.target.closest('[data-admin-supplier-id]');
    if (supplierButton) {
      await openAdminSupplier(supplierButton.dataset.adminSupplierId);
      return;
    }

    const supplierDocButton = e.target.closest('[data-admin-supplier-doc-action]');
    if (supplierDocButton) {
      await handleAdminSupplierDocumentAction(
        supplierDocButton.dataset.adminSupplierDocAction,
        supplierDocButton.dataset.documentId,
        supplierDocButton
      );
      return;
    }

    const supplierProductsAction = e.target.closest('[data-supplier-products-action]');
    if (supplierProductsAction) {
      await renderSupplierProducts();
      return;
    }

    const supplierProductEditButton = e.target.closest('[data-supplier-product-edit]');
    if (supplierProductEditButton) {
      state.currentSupplierProductId = supplierProductEditButton.dataset.supplierProductEdit;
      go('supplier-product-edit');
      return;
    }

    const supplierOrdersAction = e.target.closest('[data-supplier-orders-action]');
    if (supplierOrdersAction) {
      const action = supplierOrdersAction.dataset.supplierOrdersAction;

      if (action === 'back') {
        state.currentSupplierOrder = null;
        go('supplier-orders');
      } else if (action === 'refresh') {
        await loadSupplierOrders();
      } else if (action === 'refresh-details') {
        const current = state.currentSupplierOrder;
        if (current && current.order) {
          await openSupplierOrder(current.type, current.order.id);
        }
      }
      return;
    }

    const supplierOrderButton = e.target.closest('[data-supplier-order-id]');
    if (supplierOrderButton) {
      await openSupplierOrder(
        supplierOrderButton.dataset.supplierOrderType,
        supplierOrderButton.dataset.supplierOrderId,
        supplierOrderButton
      );
      return;
    }

    const adminOrdersAction = e.target.closest('[data-admin-orders-action]');
    if (adminOrdersAction) {
      const action = adminOrdersAction.dataset.adminOrdersAction;

      if (action === 'back') {
        state.currentAdminOrder = null;
        go('admin-orders');
      } else if (action === 'refresh' || action === 'search') {
        await loadAdminOrders();
      }
      return;
    }

    const adminOrderStatusButton = e.target.closest('[data-admin-order-status]');
    if (adminOrderStatusButton) {
      const nextStatus = adminOrderStatusButton.dataset.adminOrderStatus;
      const order = state.currentAdminOrder;

      if (!order || !nextStatus) return;

      adminOrderStatusButton.disabled = true;
      adminOrderStatusButton.textContent = 'جارٍ تحديث حالة الطلب...';

      try {
        await apiRequest(
          '/admin/orders/' + encodeURIComponent(order.id) + '/status',
          {
            method: 'PATCH',
            body: JSON.stringify({ status: nextStatus })
          }
        );

        notify('تم تحديث حالة الطلب بنجاح');
        await openAdminOrder(order.id);
      } catch (error) {
        notify(error.message);
        adminOrderStatusButton.disabled = false;
        adminOrderStatusButton.textContent = 'تحديث حالة الطلب';
      }

      return;
    }

    const adminOrderButton = e.target.closest('[data-admin-order-id]');
    if (adminOrderButton) {
      await openAdminOrder(adminOrderButton.dataset.adminOrderId, adminOrderButton);
      return;
    }

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
      if(!state.cart.length){
        notify('سلة المشتريات فارغة');
        return;
      }
      if(!state.token || !state.user){
        state.returnToCheckout = true;
        notify('سجّل الدخول أولًا لمتابعة الطلب');
        go('login');
        return;
      }
      go('checkout');
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

  if (state.token && state.user) {
    refreshSupplierDashboard();
  }

  if (state.token && localStorage.getItem('gozorx_current_order_id')) {
  restoreCurrentOrder(false);
}
})();