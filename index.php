<?php
// Start session (if needed for other features, good practice)
session_start();

// **IMPORTANT: Replace with your actual InfinityFree database credentials!**
// 
// **SQL to create the `reels` table (run this in your phpMyAdmin):**
// 
// CREATE TABLE `reels` (
//  `id` INT PRIMARY KEY AUTO_INCREMENT,
//  `filename` VARCHAR(255) NOT NULL,
//  `filepath` VARCHAR(255) NOT NULL,
//  `upload_timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
//  `likes` INT DEFAULT 0
// );
//
$db_host = "YOUR_INFINITYFREE_HOST"; // e.g., "sqlXXX.infinityfree.com"
$db_user = "YOUR_INFINITYFREE_USERNAME";
$db_pass = "YOUR_INFINITYFREE_PASSWORD";
$db_name = "YOUR_INFINITYFREE_DBNAME";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    // Do not output detailed errors in production for security reasons
    // Log error to a file or use a more robust error handling mechanism
    // For now, we'll send a generic message if it's an AJAX request or die for direct page load
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['success' => false, 'message' => 'Database connection failed. Please try again later.']);
        exit;
    } else {
        die("Database connection failed. Please check your configuration and ensure the database server is running.");
    }
}

// --- File Upload Directory ---
$upload_dir = "uploads/";
// **IMPORTANT: Create this 'uploads/' directory and ensure it has write permissions (e.g., 755).**
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        // Handle directory creation failure
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => false, 'message' => 'Failed to create upload directory. Please check permissions.']);
            exit;
        } else {
            // die("Failed to create upload directory. Please create it manually and check permissions.");
            // For now, we allow the page to load, but uploads will fail. A better approach is to show an admin error.
        }
    }
}

// --- Handling POST Requests for Uploads ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_reel') {
    header('Content-Type: application/json'); // Ensure JSON response

    // Password Verification
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    if ($password !== 'UPLOAD REEL') {
        echo json_encode(['success' => false, 'message' => 'Invalid password.']);
        exit;
    }

    // File Handling
    if (isset($_FILES['reelFile']) && $_FILES['reelFile']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['reelFile'];
        $max_file_size = 60 * 1024 * 1024; // 60MB
        $allowed_video_types = ['video/mp4', 'video/webm', 'video/ogg'];

        // Server-Side Validation: File Type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_video_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed types: MP4, WebM, Ogg.']);
            exit;
        }

        // Server-Side Validation: File Size
        if ($file['size'] > $max_file_size) {
            echo json_encode(['success' => false, 'message' => 'File is too large. Maximum size is 60MB.']);
            exit;
        }

        // File Naming & Path
        $original_filename = basename($file['name']);
        $sanitized_filename = preg_replace("/[^a-zA-Z0-9_\\.\\-]/", "_", $original_filename); // Sanitize
        $unique_filename = time() . "_" . uniqid() . "_" . $sanitized_filename;
        $target_file = $upload_dir . $unique_filename;

        // Move Uploaded File
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            // Database Insertion
            $stmt = $conn->prepare("INSERT INTO reels (filename, filepath, likes, upload_timestamp) VALUES (?, ?, 0, NOW())");
            if ($stmt) {
                $stmt->bind_param("ss", $unique_filename, $target_file);
                if ($stmt->execute()) {
                    $new_reel_id = $stmt->insert_id;
                    echo json_encode([
                        'success' => true,
                        'message' => 'Reel uploaded successfully!',
                        'reel' => [
                            'id' => $new_reel_id,
                            'filename' => $unique_filename,
                            'filepath' => $target_file,
                            'likes' => 0,
                            'upload_timestamp' => date('Y-m-d H:i:s') // Get current timestamp
                        ]
                    ]);
                    $stmt->close();
                } else {
                    // Log error: $stmt->error
                    unlink($target_file); // Remove uploaded file if DB insert fails
                    echo json_encode(['success' => false, 'message' => 'Failed to save reel information to database.']);
                }
            } else {
                 // Log error: $conn->error
                unlink($target_file); // Remove uploaded file if statement prep fails
                echo json_encode(['success' => false, 'message' => 'Database error (prepare statement).']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file. Check server permissions.']);
        }
    } else {
        $error_message = 'No file uploaded or an error occurred during upload.';
        if (isset($_FILES['reelFile']['error'])) {
            switch ($_FILES['reelFile']['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $error_message = 'File is too large (server limit).';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error_message = 'File was only partially uploaded.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error_message = 'No file was uploaded.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $error_message = 'Missing temporary folder for uploads.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $error_message = 'Failed to write file to disk.';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $error_message = 'A PHP extension stopped the file upload.';
                    break;
            }
        }
        echo json_encode(['success' => false, 'message' => $error_message]);
    }
    exit; // IMPORTANT: Stop script execution after AJAX response
}

// --- Handling POST Requests for Likes ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'like_reel') {
    header('Content-Type: application/json');

    if (isset($_POST['reel_id'])) {
        $reel_id = filter_var($_POST['reel_id'], FILTER_VALIDATE_INT);

        if ($reel_id !== false && $reel_id > 0) {
            // Update likes
            $stmt_update = $conn->prepare("UPDATE reels SET likes = likes + 1 WHERE id = ?");
            if ($stmt_update) {
                $stmt_update->bind_param("i", $reel_id);
                if ($stmt_update->execute()) {
                    $stmt_update->close();

                    // Fetch new like count
                    $stmt_fetch = $conn->prepare("SELECT likes FROM reels WHERE id = ?");
                    if ($stmt_fetch) {
                        $stmt_fetch->bind_param("i", $reel_id);
                        if ($stmt_fetch->execute()) {
                            $result_fetch = $stmt_fetch->get_result();
                            if ($row_fetch = $result_fetch->fetch_assoc()) {
                                $updated_likes = $row_fetch['likes'];
                                echo json_encode(['success' => true, 'new_like_count' => $updated_likes, 'reel_id' => $reel_id]);
                            } else {
                                // Should not happen if ID is valid and update worked, but handle defensively
                                echo json_encode(['success' => false, 'message' => 'Failed to fetch updated likes. Reel may have been deleted.']);
                            }
                            $stmt_fetch->close();
                        } else {
                            // Log error: $stmt_fetch->error
                            echo json_encode(['success' => false, 'message' => 'Failed to execute like count fetch.']);
                        }
                    } else {
                        // Log error: $conn->error
                        echo json_encode(['success' => false, 'message' => 'Database error (prepare statement for fetch).']);
                    }
                } else {
                    // Log error: $stmt_update->error
                    echo json_encode(['success' => false, 'message' => 'Failed to update likes in database.']);
                }
            } else {
                // Log error: $conn->error
                echo json_encode(['success' => false, 'message' => 'Database error (prepare statement for update).']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid Reel ID provided.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Reel ID not provided.']);
    }
    exit; // IMPORTANT: Stop script execution after AJAX response
}


// --- Logic to fetch existing reels (will be expanded later) ---
$reels = [];
$sql = "SELECT id, filename, filepath, likes, upload_timestamp FROM reels ORDER BY upload_timestamp DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $reels[] = $row;
    }
}
// $conn->close(); // Close connection if not needed further down the page (or keep open if more queries)

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Valentine's Reels</title>
    <style>
        body {
            font-family: 'Georgia', serif; /* Elegant serif font */
            background: linear-gradient(to bottom right, #ffdde1, #ee9ca7); /* Soft pink to red gradient */
            margin: 0;
            padding: 0;
            color: #333;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        .main-container {
            width: 90%;
            max-width: 1200px;
            margin: 20px auto;
            background-color: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        header {
            text-align: center;
            margin-bottom: 30px;
        }

        header h1 {
            font-family: 'Lucida Handwriting', cursive; /* Romantic cursive font */
            color: #d81b60; /* Deep pink */
            font-size: 3em;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .upload-section {
            text-align: center;
            margin-bottom: 30px;
        }

        #uploadButton {
            background-color: #e91e63; /* Bright pink */
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 1.5em;
            border-radius: 50px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.3s;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        #uploadButton:hover {
            background-color: #c2185b; /* Darker pink */
            transform: scale(1.1);
        }

        #reelGrid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .reel-placeholder { /* Style for placeholders until videos are loaded */
            background-color: #f8bbd0; /* Light pink */
            border: 2px dashed #d81b60; /* Deep pink border */
            border-radius: 10px;
            aspect-ratio: 9 / 16; /* Common reel aspect ratio */
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.2em;
            color: #c2185b;
        }

        #reelModal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8); /* Semi-transparent black */
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        #reelModal video {
            max-width: 90%;
            max-height: 80vh;
            border-radius: 10px;
            box-shadow: 0 0 30px rgba(255, 255, 255, 0.3);
        }

        #reelModal .close-modal {
            position: absolute;
            top: 20px;
            right: 30px;
            background-color: #fff;
            color: #d81b60;
            border: 2px solid #d81b60;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            font-size: 1.5em;
            cursor: pointer;
            transition: background-color 0.3s, color 0.3s;
        }

        #reelModal .close-modal:hover {
            background-color: #d81b60;
            color: #fff;
        }
        
        #reelModal #likeButton {
            background-color: #e91e63;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 1em;
            border-radius: 20px;
            cursor: pointer;
            margin-top: 10px;
            transition: background-color 0.3s;
        }

        #reelModal #likeButton:hover {
            background-color: #c2185b;
        }

        #reelModal .like-count {
            color: #fff;
            font-size: 1.2em;
            margin-left: 10px;
        }
        
        /* Basic responsiveness */
        @media (max-width: 768px) {
            header h1 {
                font-size: 2.5em;
            }

            #uploadButton {
                font-size: 1.2em;
                padding: 12px 25px;
            }

            #reelGrid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }
        }

        @media (max-width: 480px) {
            header h1 {
                font-size: 2em;
            }
            .main-container {
                width: 95%;
                padding: 15px;
            }
            #reelGrid {
                grid-template-columns: 1fr; /* Single column on very small screens */
                gap: 10px;
            }
            .reel-placeholder {
                font-size: 1em;
            }
            #reelModal video {
                max-width: 95%;
            }
            #reelModal .close-modal {
                top: 10px;
                right: 10px;
                width: 35px;
                height: 35px;
                font-size: 1.2em;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <header>
            <h1>Our Valentine's Reels</h1>
        </header>

        <div class="upload-section">
            <button id="uploadButton">Upload Reel ❤️</button>
            <input type="file" id="reelInput" style="display: none;">
        </div>

        <div id="reelGrid">
            <!-- Reels will be displayed here (dynamically by PHP/JS) -->
            <?php if (empty($reels)): ?>
                <p style="text-align: center; grid-column: 1 / -1;">No reels uploaded yet. Be the first!</p>
            <?php else: ?>
                <?php foreach ($reels as $reel): ?>
                    <div class="reel-placeholder" 
                         data-video-id="<?php echo htmlspecialchars($reel['id']); ?>"
                         data-video-src="<?php echo htmlspecialchars($reel['filepath']); ?>"
                         data-likes="<?php echo htmlspecialchars($reel['likes']); ?>">
                        <!-- You might want a thumbnail here eventually -->
                        <?php echo htmlspecialchars(substr($reel['filename'], 0, 20) . (strlen($reel['filename']) > 20 ? '...' : '')); ?>
                        <br>
                        <small>Likes: <span class="grid-like-count"><?php echo htmlspecialchars($reel['likes']); ?></span></small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div id="reelModal" style="display: none;">
            <!-- Modal content structure -->
            <div style="text-align: center;">
                 <video controls></video>
                 <div>
                    <button id="likeButton">❤️ Like</button>
                    <span class="like-count">0</span>
                 </div>
                 <button class="close-modal">X</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const uploadButton = document.getElementById('uploadButton');
            const reelInput = document.getElementById('reelInput');
            const reelGrid = document.getElementById('reelGrid');
            const reelModal = document.getElementById('reelModal');
            const modalVideo = reelModal.querySelector('video');
            const closeModalButton = reelModal.querySelector('.close-modal');
            const likeButton = document.getElementById('likeButton');
            const likeCount = reelModal.querySelector('.like-count');

            const UPLOAD_PASSWORD = "UPLOAD REEL"; // Simple password check
            const MAX_FILE_SIZE = 60 * 1024 * 1024; // 60MB
            const ALLOWED_VIDEO_TYPES = ['video/mp4', 'video/webm', 'video/ogg'];

            // 1. Upload Button Interaction
            uploadButton.addEventListener('click', () => {
                const password = prompt("Please enter the password to upload:");
                if (password === UPLOAD_PASSWORD) {
                    reelInput.click(); // Trigger hidden file input
                } else if (password !== null) { // Only show if a password was entered (not cancelled)
                    alert("Incorrect password.");
                }
            });

            // 2. File Selection and Client-Side Validation
            reelInput.addEventListener('change', (event) => {
                const file = event.target.files[0];
                if (!file) {
                    return; // No file selected
                }

                // Validate file type
                if (!ALLOWED_VIDEO_TYPES.includes(file.type)) {
                    alert(`Invalid file type. Please upload a video (${ALLOWED_VIDEO_TYPES.join(', ')}).`);
                    reelInput.value = ''; // Reset file input
                    return;
                }

                // Validate file size
                if (file.size > MAX_FILE_SIZE) {
                    alert(`File is too large. Maximum size is ${MAX_FILE_SIZE / (1024 * 1024)}MB.`);
                    reelInput.value = ''; // Reset file input
                    return;
                }

                console.log("File ready for upload:", file.name, file.type, file.size);
                // Placeholder for AJAX Upload
                // uploadFile(file); 
            });

            // Function to upload file (placeholder)
            // function uploadFile(file) {
            //     console.log(`Uploading ${file.name}...`);
            //     // AJAX call to upload file will go here
            //     // Upon successful upload, the server should return info about the reel
            //     // Then call a function to add the reel to the grid
            //     // e.g., addReelToGrid({ id: 'newReelId', src: 'path/to/new/reel.mp4', likes: 0 });
            // }

            // Placeholder for dynamically adding reels to the grid
            // function addReelToGrid(reelData) {
            //     const reelElement = document.createElement('div');
            //     reelElement.classList.add('reel-placeholder'); // Or a more specific class for loaded reels
            //     reelElement.textContent = `Reel: ${reelData.id}`; // Or display a thumbnail
            //     reelElement.dataset.videoId = reelData.id;
            //     reelElement.dataset.videoSrc = reelData.src;
            //     reelElement.dataset.likes = reelData.likes;
            //     reelElement.addEventListener('click', () => openModal(reelData.src, reelData.id, reelData.likes));
            //     reelGrid.appendChild(reelElement);
            // }
            
            // Example: Add initial placeholders (if not hardcoded in HTML) or load existing reels
            // This part would typically fetch reel data from a server on page load
            // For now, we'll make the existing placeholders clickable
                // This will now be dynamically populated by PHP, but the JS to handle clicks remains similar
                reelGrid.addEventListener('click', (event) => {
                    const reelElement = event.target.closest('.reel-placeholder');
                    if (reelElement) {
                        const videoSrc = reelElement.dataset.videoSrc;
                        const videoId = reelElement.dataset.videoId;
                        const currentLikes = reelElement.dataset.likes;
                        openModal(videoSrc, videoId, currentLikes);
                    }
            });
                
            // Function to dynamically add a new reel to the grid after successful upload
            function addReelToGrid(reelData) {
                const reelElement = document.createElement('div');
                reelElement.classList.add('reel-placeholder');
                reelElement.dataset.videoId = reelData.id;
                reelElement.dataset.videoSrc = reelData.filepath;
                reelElement.dataset.likes = reelData.likes;
                
                // Basic display of filename and likes
                reelElement.innerHTML = `
                    ${reelData.filename.substring(0, 20) + (reelData.filename.length > 20 ? '...' : '')}
                    <br>
                    <small>Likes: <span class="grid-like-count">${reelData.likes}</span></small>
                `;

                // Add click listener for the new reel
                reelElement.addEventListener('click', () => openModal(reelData.filepath, reelData.id, reelData.likes));
                
                // If there was a "No reels" message, remove it
                const noReelsMessage = reelGrid.querySelector('p');
                if (noReelsMessage) {
                    noReelsMessage.remove();
                }

                reelGrid.prepend(reelElement); // Add to the beginning of the grid
            }


            // 3. Modal Display Logic
            function openModal(videoSrc, videoId, currentLikes) {
                modalVideo.src = videoSrc;
                reelModal.style.display = 'flex';
                likeButton.dataset.videoId = videoId;
                likeCount.textContent = currentLikes;
                // modalVideo.load(); // Consider if needed
                // modalVideo.play(); // Optional
                console.log(`Opening modal for video: ${videoSrc}, ID: ${videoId}, Likes: ${currentLikes}`);
            }

            function closeModal() {
                reelModal.style.display = 'none';
                modalVideo.pause(); // Pause video when closing
                modalVideo.src = ''; // Clear source to stop background loading/playing
                likeButton.dataset.videoId = ''; // Clear videoId
            }

            closeModalButton.addEventListener('click', closeModal);

            reelModal.addEventListener('click', (event) => {
                // If the click is on the modal backdrop (not its content), close it
                if (event.target === reelModal) {
                    closeModal();
                }
            });
            
            // Prevent clicks inside the modal content (e.g., on the video or buttons) from closing the modal
            reelModal.querySelector('div').addEventListener('click', (event) => {
                event.stopPropagation();
            });


            // 5. Placeholder for Like Functionality
            likeButton.addEventListener('click', () => {
                const videoId = likeButton.dataset.videoId;
                console.log(`Like button clicked for video ID: ${videoId}`);
                // Client-side update (optimistic update)
                let currentLikes = parseInt(likeCount.textContent);
                currentLikes++;
                likeCount.textContent = currentLikes;
                
                // Update the like count on the grid item as well
                const gridItem = reelGrid.querySelector(`.reel-placeholder[data-video-id="${videoId}"] .grid-like-count`);
                if (gridItem) {
                    gridItem.textContent = currentLikes;
                }
                // Also update the data-likes attribute on the reel placeholder itself
                const reelPlaceholder = reelGrid.querySelector(`.reel-placeholder[data-video-id="${videoId}"]`);
                if(reelPlaceholder) {
                    reelPlaceholder.dataset.likes = currentLikes;
                }

                // Actual AJAX call to update likes on the server
                updateLikesOnServer(videoId);
            });

            function updateLikesOnServer(videoId) {
                console.log(`Sending like for ${videoId} to server...`);
                const formData = new FormData();
                formData.append('action', 'like_reel');
                formData.append('reel_id', videoId);

                fetch('index.php', { // Or the specific PHP handler if different
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Like successfully recorded on server. New count:', data.new_like_count);
                        // Update UI with authoritative count from server
                        likeCount.textContent = data.new_like_count;
                        const gridItemLikeCount = reelGrid.querySelector(`.reel-placeholder[data-video-id="${videoId}"] .grid-like-count`);
                        if (gridItemLikeCount) {
                            gridItemLikeCount.textContent = data.new_like_count;
                        }
                         const reelPlaceholder = reelGrid.querySelector(`.reel-placeholder[data-video-id="${videoId}"]`);
                        if(reelPlaceholder) {
                            reelPlaceholder.dataset.likes = data.new_like_count;
                        }
                    } else {
                        console.error('Failed to update like on server:', data.message);
                        // If server update failed, revert optimistic update
                        // This part is crucial for consistency if the server fails after an optimistic update
                        let currentModalLikes = parseInt(likeCount.textContent);
                        if (currentModalLikes > 0) { // Check if it was incremented
                             // Revert optimistic increment only if it was applied
                            const originalLikes = parseInt(reelGrid.querySelector(`.reel-placeholder[data-video-id="${videoId}"]`).dataset.likes) -1;
                            if (originalLikes >=0 && likeButton.dataset.lastOptimisticVideoId === videoId) {
                                likeCount.textContent = originalLikes +1 ; // It was already incremented once by optimistic
                                const gridItemLikeCount = reelGrid.querySelector(`.reel-placeholder[data-video-id="${videoId}"] .grid-like-count`);
                                if (gridItemLikeCount) {
                                     gridItemLikeCount.textContent = originalLikes +1;
                                }
                                 const reelPlaceholderToRevert = reelGrid.querySelector(`.reel-placeholder[data-video-id="${videoId}"]`);
                                if(reelPlaceholderToRevert) {
                                     reelPlaceholderToRevert.dataset.likes = originalLikes +1;
                                }
                            }
                        }
                         // To prevent multiple decrements on multiple failed calls, we can clear the last optimistic video ID
                        likeButton.dataset.lastOptimisticVideoId = null; 
                    }
                })
                .catch(error => {
                    console.error('Error sending like:', error);
                    // Also revert on network error etc.
                    let currentModalLikes = parseInt(likeCount.textContent);
                     if (currentModalLikes > 0 && likeButton.dataset.lastOptimisticVideoId === videoId) {
                        const originalLikes = parseInt(reelGrid.querySelector(`.reel-placeholder[data-video-id="${videoId}"]`).dataset.likes) -1;
                         if (originalLikes >=0) {
                            likeCount.textContent = originalLikes +1;
                            const gridItemLikeCount = reelGrid.querySelector(`.reel-placeholder[data-video-id="${videoId}"] .grid-like-count`);
                            if (gridItemLikeCount) {
                                gridItemLikeCount.textContent = originalLikes +1;
                            }
                            const reelPlaceholderToRevert = reelGrid.querySelector(`.reel-placeholder[data-video-id="${videoId}"]`);
                            if(reelPlaceholderToRevert) {
                                reelPlaceholderToRevert.dataset.likes = originalLikes +1;
                            }
                        }
                    }
                    likeButton.dataset.lastOptimisticVideoId = null;
                });
            }

            // Modify the file upload part to use FormData and AJAX
            reelInput.addEventListener('change', (event) => {
                const file = event.target.files[0];
                if (!file) return;

                if (!ALLOWED_VIDEO_TYPES.includes(file.type)) {
                    alert(`Invalid file type. Please upload a video (${ALLOWED_VIDEO_TYPES.join(', ')}).`);
                    reelInput.value = ''; return;
                }
                if (file.size > MAX_FILE_SIZE) {
                    alert(`File is too large. Maximum size is ${MAX_FILE_SIZE / (1024 * 1024)}MB.`);
                    reelInput.value = ''; return;
                }

                const formData = new FormData();
                formData.append('action', 'upload_reel');
                formData.append('password', prompt("Re-enter password for AJAX upload:", UPLOAD_PASSWORD)); // Or get from a stored var if preferred
                formData.append('reelFile', file);

                // Optional: Show a loading indicator
                uploadButton.textContent = 'Uploading...';
                uploadButton.disabled = true;

                fetch('index.php', { // Posting to the same file
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        addReelToGrid(data.reel); // Add the new reel to the grid
                    } else {
                        alert('Upload failed: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error uploading file:', error);
                    alert('An error occurred during upload. Check console.');
                })
                .finally(() => {
                    reelInput.value = ''; // Reset file input
                    uploadButton.textContent = 'Upload Reel ❤️'; // Reset button text
                    uploadButton.disabled = false; // Re-enable button
                });
            });
            // Remove the old console log for "File ready for upload" as it's now handled by the AJAX call
            // console.log("File ready for upload:", file.name, file.type, file.size); 
            // uploadFile(file); // This was a placeholder, now replaced by fetch

        });
    </script>
</body>
</html>
