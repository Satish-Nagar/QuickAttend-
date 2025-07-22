// Assignment Modal JS
document.addEventListener('DOMContentLoaded', function() {
  let previewData = null;
  let previewHeaders = null;

  // Preview button handler
  document.getElementById('previewBtn').addEventListener('click', function() {
    const fileInput = document.getElementById('scores_file');
    const file = fileInput.files[0];
    if (!file) {
      alert('Please select a file to preview.');
      return;
    }
    const formData = new FormData();
    formData.append('action', 'preview_file');
    formData.append('scores_file', file);
    fetch('assignment_modal.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        previewData = data.rows;
        previewHeaders = data.headers;
        renderPreviewTable(previewHeaders, previewData);
        document.getElementById('exportBtn').style.display = 'inline-block';
      } else {
        alert(data.error || 'Error processing file.');
        document.getElementById('previewTable').innerHTML = '';
        document.getElementById('exportBtn').style.display = 'none';
      }
    });
  });

  // Export button handler
  document.getElementById('exportBtn').onclick = function() {
    if (!previewData || !previewHeaders) return;
    const formData = new FormData();
    formData.append('action', 'export_file');
    formData.append('subject_name', document.getElementById('subject_name').value);
    formData.append('faculty_name', document.getElementById('faculty_name').value);
    formData.append('headers', JSON.stringify(previewHeaders));
    formData.append('rows', JSON.stringify(previewData));
    fetch('assignment_modal.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.success && data.file && data.filename) {
        // Download the file
        const link = document.createElement('a');
        link.href = 'data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,' + data.file;
        link.download = data.filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      } else {
        alert(data.error || 'Export failed.');
      }
    });
  };

  function renderPreviewTable(headers, rows) {
    let html = '<table class="table table-bordered"><thead><tr>';
    headers.forEach(h => html += `<th>${h}</th>`);
    html += '</tr></thead><tbody>';
    rows.forEach(row => {
      html += '<tr>';
      headers.forEach(h => html += `<td>${row[h]}</td>`);
      html += '</tr>';
    });
    html += '</tbody></table>';
    document.getElementById('previewTable').innerHTML = html;
  }

  // Reset preview on modal close
  const modalEl = document.getElementById('assignmentModal');
  modalEl.addEventListener('hidden.bs.modal', function () {
    previewData = null;
    previewHeaders = null;
    document.getElementById('previewTable').innerHTML = '';
    document.getElementById('assignmentForm').reset();
    document.getElementById('exportBtn').style.display = 'none';
  });
}); 