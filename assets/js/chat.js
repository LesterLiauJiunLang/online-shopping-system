// chat.js - Chat functionality using jQuery

$(document).ready(function() {
    // Auto-resize textarea
    function autoResizeTextarea(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }
    
    // Scroll to bottom of messages
    function scrollToBottom() {
        const messagesArea = $('#messagesArea');
        if (messagesArea.length) {
            messagesArea.scrollTop(messagesArea[0].scrollHeight);
        }
    }
    
    // Initialize
    scrollToBottom();
    
    // Auto-resize message input
    $('#messageInput').on('input', function() {
        autoResizeTextarea(this);
    });
    
    $('#broadcastMessage').on('input', function() {
        autoResizeTextarea(this);
    });
    
    // Reply functionality
    $(document).on('click', '.btn-reply', function() {
        const messageId = $(this).data('msgid');
        const messageText = $(this).data('msgtext');
        
        $('#replyTo').val(messageId);
        $('#replyText').text(messageText);
        $('#replyPreview').show();
        $('#messageInput').focus();
        
        scrollToBottom();
    });
    
    // Cancel reply
    $('#cancelReplyBtn').on('click', function() {
        $('#replyTo').val('');
        $('#replyPreview').hide();
        $('#messageInput').focus();
    });
    
    // Delete message
    $(document).on('click', '.btn-delete', function() {
        const messageId = $(this).data('msgid');
        
        if (confirm('Are you sure you want to delete this message?')) {
            const form = $('<form>').attr({
                method: 'POST',
                style: 'display: none;'
            });
            
            $('<input>').attr({
                type: 'hidden',
                name: 'delete_message',
                value: '1'
            }).appendTo(form);
            
            $('<input>').attr({
                type: 'hidden',
                name: 'message_id',
                value: messageId
            }).appendTo(form);
            
            $('<input>').attr({
                type: 'hidden',
                name: 'csrf_token',
                value: $('input[name="csrf_token"]').val()
            }).appendTo(form);
            
            $('body').append(form);
            form.submit();
        }
    });
    
    // Broadcast modal
    $('#broadcastBtn').on('click', function() {
        $('#broadcastModal').show();
    });
    
    $('#closeBroadcastModal, #cancelBroadcastBtn').on('click', function() {
        $('#broadcastModal').hide();
        $('#broadcastMessage').val('');
    });
    
    // Refresh chat
    $('#refreshBtn').on('click', function() {
        window.location.reload();
    });
    
    // Auto-hide alerts
    setTimeout(function() {
        $('.alert').fadeOut(300, function() {
            $(this).remove();
        });
    }, 5000);
    
    // Form validation
    $('.message-form').on('submit', function(e) {
        const messageInput = $('#messageInput');
        if (messageInput.val().trim() === '') {
            e.preventDefault();
            alert('Please enter a message');
            messageInput.focus();
        }
    });
    
    $('.broadcast-form').on('submit', function(e) {
        const broadcastMessage = $('#broadcastMessage');
        if (broadcastMessage.val().trim() === '') {
            e.preventDefault();
            alert('Please enter a broadcast message');
            broadcastMessage.focus();
        }
    });
    
    // Handle Ctrl+Enter to send message
    $(document).on('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            const activeElement = document.activeElement;
            if (activeElement && activeElement.id === 'messageInput') {
                if ($('#messageInput').val().trim() !== '') {
                    $('.message-form').submit();
                }
            }
        }
    });
    
    // Close modal when clicking outside
    $(window).on('click', function(e) {
        const modal = $('#broadcastModal');
        if (e.target === modal[0]) {
            modal.hide();
            $('#broadcastMessage').val('');
        }
    });
});