$(document).ready(function(){
    // Function to load messages
    function loadMessages(){
        $.ajax({
            url: "get_messages.php",
            cache: false,
            success: function(data){
                // Clear current messages
                $("#chatbox").empty();
                // Split messages by newline and display
                var messages = data.split("\n");
                messages.forEach(function(message) {
                    if (message.trim() !== "") { // Avoid displaying empty lines
                        // Sanitize message content before appending to prevent XSS
                        var sanitizedMessage = $("<div/>").text(message).html();
                        $("#chatbox").append("<div class='message'>" + sanitizedMessage + "</div>");
                    }
                });
                // Auto-scroll to the bottom of the chatbox
                $("#chatbox").scrollTop($("#chatbox")[0].scrollHeight);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("Error loading messages: " + textStatus, errorThrown);
                // Optionally, display an error message to the user in the chatbox
                // $("#chatbox").append("<div class='message error'>Error loading messages. Please try again later.</div>");
            }
        });
    }

    // Load messages on page load
    loadMessages();

    // Set interval to reload messages every 3 seconds (3000 milliseconds)
    setInterval(loadMessages, 3000);

    // Handle form submission
    $("#submitmsg").click(function(){
        var clientmsg = $("#usermsg").val();
        if(clientmsg.trim() === ""){
            // Optionally, provide feedback if the message is empty
            // alert("Please enter a message.");
            return false; // Prevent form submission
        }

        $.ajax({
            type: "POST",
            url: "send_message.php",
            data: { usermsg: clientmsg },
            success: function(data){
                $("#usermsg").val(""); // Clear input field
                // Don't need to call loadMessages() here immediately, 
                // as setInterval will pick it up. Or call it for instant update:
                // loadMessages(); 
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("Error sending message: " + textStatus, errorThrown);
                // Optionally, display an error to the user
                // alert("Error sending message.");
            }
        });
        return false; // Prevent default form submission
    });

    // Allow submitting message by pressing Enter key
    $("#usermsg").keypress(function(e){
        if(e.which == 13) { // 13 is the Enter key
            $("#submitmsg").click();
            return false; // Prevent default form submission (which would reload the page)
        }
    });
});
