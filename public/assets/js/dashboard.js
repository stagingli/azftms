// dashboard.js

document.addEventListener('DOMContentLoaded', function() {
    // Highlight new entries if 'highlight' parameter is set in URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('highlight')) {
        const entryId = urlParams.get('highlight');
        const entryElement = document.getElementById('entry-' + entryId);
        if (entryElement) {
            entryElement.classList.add('badge bg-warning-highlight');
            entryElement.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            setTimeout(() => {
                entryElement.classList.remove('badge bg-warning-highlight');
            }, 2000);
        }
    }

    // Auto-hide success alerts after 3 seconds
    document.querySelectorAll('.alert-success').forEach(alert => {
        setTimeout(() => {
            alert.classList.add('fade-out');
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        }, 3000);
    });
});

// Toggle comments using CSS classes (ohne Inline-Styles)
function toggleComments(entryId) {
    const commentsContainer = document.getElementById('comments-' + entryId);
    if (!commentsContainer) return;
    
    if (commentsContainer.classList.contains('d-none')) {
        // Einblenden
        commentsContainer.classList.remove('d-none');
        commentsContainer.classList.add('fade-in');
        setTimeout(() => {
            commentsContainer.classList.remove('fade-in');
        }, 300);
    } else {
        // Ausblenden
        commentsContainer.classList.add('fade-out');
        setTimeout(() => {
            commentsContainer.classList.remove('fade-out');
            commentsContainer.classList.add('d-none');
        }, 300);
    }
}

// Toggle inline edit form for an entry
function toggleEditEntry(entryId) {
    const titleElem = document.getElementById('entry-title-' + entryId);
    const editForm = document.getElementById('edit-entry-form-' + entryId);
    if (!titleElem || !editForm) return;

    if (editForm.classList.contains('d-none')) {
        // Formular anzeigen, Titel ausblenden
        editForm.classList.remove('d-none');
        titleElem.classList.add('d-none');
    } else {
        // Formular ausblenden, Titel anzeigen
        editForm.classList.add('d-none');
        titleElem.classList.remove('d-none');
    }
}

// Toggle inline edit form for a comment
function toggleEditComment(commentId) {
    const commentText = document.getElementById('comment-text-' + commentId);
    const editForm = document.getElementById('edit-comment-form-' + commentId);
    if (!commentText || !editForm) return;

    if (editForm.classList.contains('d-none')) {
        // Formular anzeigen, Text ausblenden
        editForm.classList.remove('d-none');
        commentText.classList.add('d-none');
    } else {
        // Formular ausblenden, Text anzeigen
        editForm.classList.add('d-none');
        commentText.classList.remove('d-none');
    }
}
