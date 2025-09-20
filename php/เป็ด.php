I have improved your code to make the file selection a dropdown menu, which is a common and user-friendly way to handle options. This change consolidates the two file type buttons into a single, clean dropdown menu.

### Summary of Changes:

1.  **HTML Modification**: I replaced the two file selection buttons with a `<select>` dropdown menu. This provides a more compact and professional look.
2.  **CSS Adjustment**: I've updated the CSS to style the new `<select>` element to match the rest of your design, ensuring a cohesive appearance.
3.  **JavaScript Logic**: The JavaScript has been rewritten to handle the new dropdown menu. Instead of checking for a button's `active` class, it now checks the value of the selected option in the dropdown to determine the file type. The action buttons (Download, Email, Messenger) are now enabled only when a valid file type is chosen from the dropdown.

-----

### Revised and Enhanced Code

Here is the complete, working code with the dropdown menu for file selection.

```html
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบแชร์ไฟล์</title>
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #0d1a44;
            color: white;
            flex-direction: column;
        }

        #shareButton {
            padding: 12px 24px;
            font-size: 18px;
            cursor: pointer;
            border: none;
            border-radius: 8px;
            background-color: #3498db;
            color: white;
            transition: background-color 0.3s, transform 0.2s;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        #shareButton:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: #23a0e6;
            margin: 15% auto;
            padding: 2rem;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            text-align: center;
            border-radius: 12px;
            position: relative;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .close-button {
            color: white;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            top: 10px;
            right: 20px;
            transition: color 0.2s;
        }

        .close-button:hover,
        .close-button:focus {
            color: #f1f1f1;
            text-decoration: none;
            cursor: pointer;
        }

        h2 {
            font-size: 24px;
            margin-bottom: 25px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }

        .file-options {
            margin-bottom: 25px;
        }

        .file-options select {
            padding: 12px 15px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: #1a7ab4;
            color: white;
            width: 80%;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .file-options select:focus {
            outline: none;
            border-color: #27ae60;
            box-shadow: 0 0 10px rgba(46, 204, 113, 0.5);
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .action-buttons button {
            padding: 12px 24px;
            font-size: 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            background-color: #3498db;
            color: white;
            transition: background-color 0.3s, transform 0.2s;
        }

        .action-buttons button:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        .action-buttons button:disabled {
            background-color: #7f8c8d;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

    <button id="shareButton">แชร์</button>

    <div id="shareModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>เลือกไฟล์</h2>
            <div class="file-options">
                <select id="fileSelect">
                    <option value="">-- เลือกไฟล์ --</option>
                    <option value="pdf">PDF</option>
                    <option value="excel">Excel</option>
                </select>
            </div>
            <div class="action-buttons">
                <button id="downloadButton" disabled>ดาวน์โหลด</button>
                <button id="emailButton" disabled>อีเมล</button>
                <button id="messengerButton" disabled>Messenger</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const shareButton = document.getElementById('shareButton');
            const shareModal = document.getElementById('shareModal');
            const closeButton = document.querySelector('.close-button');
            const fileSelect = document.getElementById('fileSelect');
            const downloadButton = document.getElementById('downloadButton');
            const emailButton = document.getElementById('emailButton');
            const messengerButton = document.getElementById('messengerButton');

            // Function to toggle action buttons' disabled state
            const toggleActionButtons = (state) => {
                downloadButton.disabled = !state;
                emailButton.disabled = !state;
                messengerButton.disabled = !state;
            };
            
            // Open the modal
            shareButton.onclick = function() {
                shareModal.style.display = 'flex';
                toggleActionButtons(false); // Disable buttons initially
                fileSelect.value = ''; // Reset dropdown
            };

            // Close the modal
            closeButton.onclick = function() {
                shareModal.style.display = 'none';
            };

            // Close the modal by clicking outside
            window.onclick = function(event) {
                if (event.target == shareModal) {
                    shareModal.style.display = 'none';
                }
            };
            
            // Handle dropdown change
            fileSelect.onchange = function() {
                const selectedFile = fileSelect.value;
                if (selectedFile) {
                    alert(`คุณเลือกไฟล์ประเภท ${selectedFile.toUpperCase()}`);
                    toggleActionButtons(true);
                } else {
                    toggleActionButtons(false);
                }
            };

            // Download action
            downloadButton.onclick = function() {
                const selectedFile = fileSelect.value;
                alert(`กำลังดาวน์โหลดไฟล์ ${selectedFile.toUpperCase()}...`);
                // In a real application, you'd add the actual download logic here.
                // For example: window.location.href = `path/to/download.${selectedFile}`;
            };

            // Email action
            emailButton.onclick = function() {
                const selectedFile = fileSelect.value;
                alert(`กำลังส่งไฟล์ ${selectedFile.toUpperCase()} ทางอีเมล...`);
                // In a real application, you'd add the actual email logic here.
            };

            // Messenger action
            messengerButton.onclick = function() {
                const selectedFile = fileSelect.value;
                alert(`กำลังแชร์ไฟล์ ${selectedFile.toUpperCase()} บน Messenger...`);
                // In a real application, you'd add the actual Messenger share logic here.
            };
        });
    </script>
</body>
</html>
```