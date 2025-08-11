$(document).ready(function() {
    const data = [
        { code: '230', nom: 'Ali', date: '2025-02-25', type: 'Ovin', deleted: false },
        { code: '231', nom: 'Samira', date: '2025-02-24', type: 'Caprin', deleted: false },
        { code: '232', nom: 'Youssef', date: '2025-02-23', type: 'Bovin', deleted: false }
    ];

    $('#searchBtn').on('click', function () {
        let query = $('#searchInput').val().toLowerCase();
        let filtered = data.filter(d => d.code.includes(query));
        let html = '';
        filtered.forEach((row, idx) => {
            html += `<tr class="${row.deleted ? 'text-muted bg-light' : ''}">`;
            html += `<td>${row.code}</td><td>${row.nom}</td><td>${row.date}</td><td>${row.type}</td>`;
            html += `<td><button class="btn btn-sm btn-danger delete-btn" data-index="${idx}">Supprimer</button></td>`;
            html += '</tr>';
        });
        $('#resultsBody').html(html);
        $('#resultsContainer').show();
    });

    $(document).on('click', '.delete-btn', function () {
        let index = $(this).data('index');
        data[index].deleted = true;
        $('#searchBtn').click();
    });

    const table = $('#kt_datatable_results').DataTable({
        responsive: true,
        pageLength: 3,
        lengthMenu: [3, 10, 25, 50, 100],
        language: {
            url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/French.json"
        },
        drawCallback: function () {
            // Move .dataTables_info under .dataTables_length
            const info = $('#kt_datatable_results_info');
            const length = $('#kt_datatable_results_length');
            if (info.length && length.length) {
                length.after(info);
            }
        },
        ordering: false,
        //columnDefs: [
        //    { targets: [7], orderable: false }
        //]
    });

    // Recherche colonne par colonne
    $('#kt_datatable_results thead').on('input', '.filter-input', function () {
        let colIndex = $(this).parent().index();
        table.column(colIndex).search(this.value).draw();
    });

    // Suppression logique
    $('#kt_datatable_results').on('click', '.delete-btn', function () {
        const row = $(this).closest('tr');
        row.addClass('text-muted text-decoration-line-through');
        $(this).remove(); // supprimer le bouton
    });


});
