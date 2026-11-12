(() => {
  const app = document.getElementById('app');
  const toast = document.getElementById('toast');
  const homeMarkup = app.innerHTML;
  const state = { page: 'home', suppliers: [], products: [], language: 'ar' };
  const categories = ['أشجار الفاكهة والشتلات المطعومة','أشجار الزينة واللاندسكيب','أشجار الغابات والأخشاب','الزيتون والموالح والمحاصيل المتخصصة'];
  const demoProducts = [
    {name:'شتلة ليمون مطعوم',category:categories[0],price:180,moq:10,icon:'🍋'},
    {name:'شتلة زيتون بيكوال',category:categories[3],price:120,moq:25,icon:'🫒'},
    {name:'شتلة تيكوما للزينة',category:categories[1],price:95,moq:20,icon:'🌼'},
    {name:'شتلات كازورينا',category:categories[2],price:35,moq:100,icon:'🌲'}
  ];
  function notify(msg){toast.textContent=msg;toast.classList.add('show');setTimeout(()=>toast.classList.remove('show'),2600);}
  function go(page){state.page=page;render();window.scrollTo({top:0,behavior:'smooth'});}
  function field(label,name,type='text',required=true){return '<div class="field"><label>'+label+'</label><input name="'+name+'" type="'+type+'" '+(required?'required':'')+'></div>';}
  function shell(title,subtitle,body){return '<section class="page"><div class="page-title"><span class="eyebrow">GOZORX</span><h1>'+title+'</h1><p>'+subtitle+'</p></div>'+body+'</section>';}
  function renderHome(){return homeMarkup;}
  function render(){
    if(state.page==='home'){app.innerHTML=renderHome();return;}
    if(state.page==='market'){
      app.innerHTML=shell('السوق الزراعي','تصفح المنتجات المعروضة — بيانات تجريبية للعرض فقط.','<div class="toolbar"><input id="searchProduct" placeholder="ابحث عن منتج..."><select id="categoryFilter"><option value="">كل الأقسام</option>'+categories.map(c=>'<option>'+c+'</option>').join('')+'</select></div><div class="product-grid" id="products"></div>');
      const draw=()=>{const q=document.getElementById('searchProduct').value.toLowerCase(),c=document.getElementById('categoryFilter').value;document.getElementById('products').innerHTML=demoProducts.filter(p=>(p.name.toLowerCase().includes(q)||p.category.includes(q))&&(!c||p.category===c)).map(p=>'<article class="product"><div class="product-img">'+p.icon+'</div><div class="product-body"><h3>'+p.name+'</h3><p>'+p.category+'</p><div class="price">'+p.price+' جنيه / وحدة</div><div class="product-meta"><span>الحد الأدنى: '+p.moq+'</span><span>عرض تجريبي</span></div></div></article>').join('')||'<div class="empty">لا توجد نتائج</div>';};
      document.getElementById('searchProduct').addEventListener('input',draw);document.getElementById('categoryFilter').addEventListener('change',draw);draw();return;
    }
    if(state.page==='join'){
      app.innerHTML=shell('طلب انضمام مورد','قدّم بياناتك للمراجعة. لن تتمكن من نشر المنتجات قبل موافقة الإدارة.','<form class="panel form-grid" id="supplierForm">'+field('اسم المسؤول','contact_name')+field('اسم المشتل / الشركة','business_name')+field('رقم الهاتف','phone','tel')+field('البريد الإلكتروني','email','email')+'<div class="field"><label>المحافظة</label><input name="governorate" required></div><div class="field"><label>المدينة</label><input name="city" required></div><div class="field full"><label>نبذة عن النشاط والمنتجات</label><textarea name="description" required></textarea></div><div class="field full"><button class="primary">إرسال طلب الانضمام</button><small class="hint">هذه نسخة عرض: الطلب لا يُحفظ على خادم ولا يُرسل للإدارة فعليًا.</small></div></form>');
      document.getElementById('supplierForm').addEventListener('submit',e=>{e.preventDefault();state.suppliers.push(Object.fromEntries(new FormData(e.target)));notify('تم تسجيل الطلب في هذه الجلسة التجريبية فقط');e.target.reset();});return;
    }
    if(state.page==='login'){
      app.innerHTML=shell('تسجيل الدخول','واجهة تجريبية — لا يوجد حساب حقيقي أو مصادقة بعد.','<form class="panel form-grid" id="loginForm">'+field('البريد الإلكتروني','email','email')+field('كلمة المرور','password','password')+'<div class="field full"><button class="primary">دخول تجريبي</button><small class="hint">تسجيل الدخول الحقيقي سيتم تفعيله بعد تجهيز الخادم وقاعدة البيانات.</small></div></form><p><button class="textbtn" data-page="register">إنشاء حساب مشتري</button></p>');
      document.getElementById('loginForm').addEventListener('submit',e=>{e.preventDefault();notify('تسجيل الدخول غير مفعّل في النسخة التجريبية');});return;
    }
    if(state.page==='register'){
      app.innerHTML=shell('إنشاء حساب مشتري','التسجيل الحقيقي سيُفعّل في المرحلة الخلفية.','<form class="panel form-grid" id="registerForm">'+field('الاسم بالكامل','name')+field('رقم الهاتف','phone','tel')+field('البريد الإلكتروني','email','email')+field('كلمة المرور','password','password')+'<div class="field"><label>المحافظة</label><input name="governorate" required></div><div class="field"><label>المدينة</label><input name="city" required></div><div class="field full"><button class="primary">إنشاء حساب تجريبي</button></div></form>');
      document.getElementById('registerForm').addEventListener('submit',e=>{e.preventDefault();notify('الحسابات الحقيقية لم تُفعّل بعد');});return;
    }
    if(state.page==='rfq'||state.page==='nursery'){
      const nursery=state.page==='nursery';
      app.innerHTML=shell(nursery?'طلبات المشاتل':'طلب عرض سعر لمشروع','أرسل احتياجاتك وسيتواصل معك فريق GOZORX بعد تفعيل النظام.','<form class="panel form-grid" id="requestForm">'+field('الاسم','name')+field('رقم الهاتف','phone','tel')+field('البريد الإلكتروني','email','email')+field('المحافظة','governorate')+'<div class="field full"><label>الأصناف والكميات المطلوبة</label><textarea name="details" required></textarea></div><div class="field full"><button class="primary">إرسال الطلب</button><small class="hint">نموذج تجريبي ولا يرسل بيانات إلى خادم.</small></div></form>');
      document.getElementById('requestForm').addEventListener('submit',e=>{e.preventDefault();notify('النموذج تجريبي — لم يتم إرسال الطلب فعليًا');e.target.reset();});return;
    }
    app.innerHTML=shell('قريبًا','هذه الوظيفة ضمن مراحل تطوير GOZORX.','<div class="panel">سيتم تفعيلها بعد تجهيز الخادم وقاعدة البيانات.</div>');
  }
  document.addEventListener('click',e=>{const target=e.target.closest('[data-page]');if(target){e.preventDefault();go(target.dataset.page);}});
  const lang=document.getElementById('langBtn');if(lang)lang.addEventListener('click',()=>notify('الواجهة الإنجليزية قيد التطوير'));
  go('home');
})();