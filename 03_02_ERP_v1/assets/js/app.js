async function apiFetch(endpoint, method = 'GET', body = null) {
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json'
        }
    };
    if (body) options.body = JSON.stringify(body);

    try {
        const response = await fetch(`api${endpoint}`, options);
        if (response.status === 401) {
            window.location.href = 'login.html';
            return { error: 'Session expired' };
        }
        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        return { error: 'Network error or server down' };
    }
}

// Formatters
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function getStatusBadge(status) {
    const s = status ? status.toLowerCase() : '';
    if (s.includes('pendiente')) return `<span class="status-badge status-pending">Pendiente</span>`;
    if (s.includes('completado')) return `<span class="status-badge status-success">Completado</span>`;
    if (s.includes('cancelado')) return `<span class="status-badge status-danger">Cancelado</span>`;
    return `<span class="status-badge status-info">${status || 'Desconocido'}</span>`;
}
