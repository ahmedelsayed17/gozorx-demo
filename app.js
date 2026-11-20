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
          ? '<button class="outline" data-page="admin-orders" type="button">إدارة الطلبات</button> <button class="outline" data-page="admin-payments" type="button">مراجعة المدفوعات</button> '
          : '<button class="outline" data-page="my-orders" type="button">طلباتي</button> ') +
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



  const adminOrderStatusLabels = {
    pending_payment: 'بانتظار العربون',
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
              <div><span>هاتف العميل</span><strong>${escapeAdminHtml(buyer.phone || order.recipient_phone || '—')}</strong></div>
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

    app.innerHTML = shell(
      'تفاصيل الطلب',
      'مراجعة بيانات العميل والطلب والموردين وحالة العربون.',
      `
        <div class="admin-orders-toolbar">
          <button class="outline" type="button" data-admin-orders-action="back">العودة إلى قائمة الطلبات</button>
        </div>
        <section class="panel admin-order-detail">
          <h3>الطلب رقم: ${escapeAdminHtml(order.order_number || order.id)}</h3>
          <div class="admin-order-grid">
            <div><span>حالة الطلب</span><strong>${escapeAdminHtml(statusLabel)}</strong></div>
            <div><span>تاريخ الإنشاء</span><strong>${escapeAdminHtml(order.created_at ? new Date(order.created_at).toLocaleString('ar-EG') : '—')}</strong></div>
            <div><span>اسم العميل</span><strong>${escapeAdminHtml(buyer.full_name || buyer.name || '—')}</strong></div>
            <div><span>هاتف العميل</span><strong>${escapeAdminHtml(buyer.phone || '—')}</strong></div>
            <div><span>البريد الإلكتروني</span><strong>${escapeAdminHtml(buyer.email || '—')}</strong></div>
            <div><span>اسم المستلم</span><strong>${escapeAdminHtml(order.recipient_name || '—')}</strong></div>
            <div><span>هاتف المستلم</span><strong>${escapeAdminHtml(order.recipient_phone || '—')}</strong></div>
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
      `
    );
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
        confirmed: 'تم تأكيد الطلب',
        processing: 'قيد التجهيز',
        shipped: 'تم الشحن',
        delivered: 'تم التسليم',
        cancelled: 'ملغي',
        completed: 'مكتمل'
      };

      container.innerHTML = orders.map(order => {
        const payment = order.deposit_transaction || null;
        let receiptStatus = 'لم يتم رفع الإيصال';

        if (order.receipt_uploaded) {
          receiptStatus = payment?.status === 'rejected'
            ? 'الإيصال مرفوض - راجع سبب الرفض'
            : payment?.status === 'approved'
              ? 'تم اعتماد العربون'
              : 'الإيصال بانتظار مراجعة الإدارة';
        }

        const createdAt = order.created_at
          ? new Date(order.created_at).toLocaleString('ar-EG')
          : '—';

        const orderStatus = labels[order.status] || order.status || 'غير محدد';
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
              <div><span>إجمالي الطلب</span><strong>${Number(order.grand_total || 0).toLocaleString('ar-EG')} جنيه</strong></div>
              <div><span>العربون (30%)</span><strong>${Number(order.deposit_amount || 0).toLocaleString('ar-EG')} جنيه</strong></div>
              <div><span>المتبقي عند الاستلام</span><strong>${Number(order.balance_amount || 0).toLocaleString('ar-EG')} جنيه</strong></div>
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
    if (!state.token || !state.user || state.user.role === 'admin') {
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
    if (!state.token || !state.user || state.user.role === 'admin') {
      notify('سجّل الدخول بحساب المشتري لعرض الطلب.');
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

  function renderHome(){return homeMarkup;}
  function render(){
    if(state.page==='home'){app.innerHTML=renderHome();return;}

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

      const reviewNote = payment?.review_note
        ? '<div class="payment-notice"><strong>سبب الرفض أو ملاحظة الإدارة:</strong><p>' +
          escapeAdminHtml(payment.review_note) + '</p></div>'
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
            <p>إجمالي الطلب: <strong>${Number(order.grand_total || 0).toLocaleString('ar-EG')} جنيه</strong></p>
            <p>العربون (30%): <strong>${Number(order.deposit_amount || 0).toLocaleString('ar-EG')} جنيه</strong></p>
            <p>المتبقي عند الاستلام (70%): <strong>${Number(order.balance_amount || 0).toLocaleString('ar-EG')} جنيه</strong></p>
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

          if(!result.order){
            throw new Error('تم الاتصال بالخادم لكن لم يتم استلام بيانات الطلب.');
          }

          state.currentOrder = result.order;
          state.currentOrder.receipt_uploaded = false;
            localStorage.setItem(
              'gozorx_current_order_id',
              String(result.order.id)
            );
          state.cart = [];
          updateCartCount();

          notify('تم إنشاء الطلب وحجز الكميات لمدة 24 ساعة');
          go('order-payment');
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

  if (state.token && localStorage.getItem('gozorx_current_order_id')) {
    restoreCurrentOrder(true);
  }
})();