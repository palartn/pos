/* طباعة محتوى التقرير فقط (بدون قائمة النظام والفلاتر والأزرار) */
window.rpPrint = function (id) {
    var src = document.getElementById(id);

    if (!src) {
        window.print();
        return;
    }

    var PRINT_CSS =
        '@page { size: A4; margin: 12mm; }' +
        '* { -webkit-print-color-adjust: exact; print-color-adjust: exact; box-sizing: border-box; }' +
        'body { margin: 0; background: #fff; color: #000; font-family: Tahoma, Arial, sans-serif; font-size: 12px; }' +
        '.rp { gap: .75rem; }' +
        '.rp-print-head { display: block !important; text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 8px; }' +
        '.rp-print-head h1 { margin: 0 0 4px; font-size: 20px; }' +
        '.rp-print-head div { font-size: 12px; color: #333; }' +
        '.rp-card, .rp-stat { box-shadow: none !important; border: 1px solid #bbb !important; break-inside: avoid; }' +
        '.rp-card h3 { font-size: 14px; }' +
        '.rp-stats { grid-template-columns: repeat(4, 1fr) !important; }' +
        '.rp-stat strong { font-size: 15px !important; }' +
        '.rp-table { font-size: 11px; }' +
        '.rp-table tr { break-inside: avoid; }' +
        '.rp-table thead { display: table-header-group; }' +
        '.rp-table tbody tr:hover { background: none; }' +
        '.rp-scroll { overflow: visible !important; }' +
        '.rp-chart { overflow: visible !important; height: 140px !important; }';

    var links = Array.prototype.slice.call(document.querySelectorAll('link[href*="reports.css"]'))
        .map(function (l) { return '<link rel="stylesheet" href="' + l.href + '">'; })
        .join('');

    var frame = document.createElement('iframe');
    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';

    var printed = false;
    var run = function () {
        if (printed) { return; }
        printed = true;
        frame.contentWindow.focus();
        frame.contentWindow.print();
        setTimeout(function () { frame.remove(); }, 60000);
    };

    frame.onload = run;
    document.body.appendChild(frame);

    var doc = frame.contentWindow.document;
    doc.open();
    doc.write(
        '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>' +
        (document.title || 'تقرير') + '</title>' + links + '<style>' + PRINT_CSS + '</style></head>' +
        '<body><div class="rp">' + src.innerHTML + '</div></body></html>'
    );
    doc.close();

    // احتياط إن لم يُطلق المتصفح حدث التحميل
    setTimeout(run, 1500);
};
