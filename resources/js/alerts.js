import Swal from 'sweetalert2';

/**
 * Helper central de alertas do upMusic — todo feedback do sistema usa SweetAlert2.
 * Cores da marca: preto (#000000) e laranja (#ff8c1e).
 */

const BRAND_ORANGE = '#ff8c1e';
const BRAND_BLACK = '#000000';

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2500,
    timerProgressBar: true,
});

export function notifySuccess(message, title = 'Sucesso') {
    return Toast.fire({ icon: 'success', title, text: message });
}

export function notifyError(message, title = 'Ops') {
    return Swal.fire({
        icon: 'error',
        title,
        text: message,
        confirmButtonColor: BRAND_ORANGE,
    });
}

export function notifyInfo(message, title = '') {
    return Toast.fire({ icon: 'info', title, text: message });
}

/**
 * Confirmação de ação (destrutiva por padrão). Retorna Promise<boolean>.
 */
export async function confirmAction({
    title = 'Confirmar ação',
    text = 'Deseja realmente continuar?',
    confirmText = 'Confirmar',
    cancelText = 'Cancelar',
    icon = 'warning',
} = {}) {
    const result = await Swal.fire({
        title,
        text,
        icon,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        confirmButtonColor: BRAND_ORANGE,
        cancelButtonColor: BRAND_BLACK,
        reverseButtons: true,
    });
    return result.isConfirmed;
}

export function showLoading(title = 'Processando...') {
    Swal.fire({
        title,
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
    });
}

export function closeLoading() {
    Swal.close();
}

// Disponibiliza globalmente para uso inline em Blade/Alpine.
window.Swal = Swal;
window.upAlerts = { notifySuccess, notifyError, notifyInfo, confirmAction, showLoading, closeLoading };

/**
 * Confirmação declarativa: qualquer <form data-confirm="mensagem"> pede confirmação
 * via SweetAlert2 antes de enviar. Uso típico em botões de exclusão.
 */
document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (form.matches('[data-confirm]') && !form.dataset.confirmed) {
        e.preventDefault();
        const ok = await confirmAction({
            title: form.dataset.confirmTitle || 'Confirmar exclusão',
            text: form.dataset.confirm,
            confirmText: form.dataset.confirmButton || 'Excluir',
            icon: 'warning',
        });
        if (ok) {
            form.dataset.confirmed = '1';
            form.submit();
        }
    }
}, true);
