<style>
    .accordion-panel {
        display: none;
        padding: 8px;
        margin-top: 6px;
        background-color: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
    }

    .accordion-btn {
        background: none;
        border: none;
        color: #3498db;
        cursor: pointer;
        font-size: 14px;
        margin-left: 6px;
        padding: 0 4px;
    }

    .accordion-btn:hover {
        color: #2980b9;
    }

    .accordion-btn.active {
        color: #27ae60;
    }

    .question-item {
        margin-bottom: 5px;
        padding: 5px 8px;
        background-color: #f8f9fa;
        border-radius: 4px;
        border-left: 3px solid #3498db;
        display: flex;
        align-items: flex-start;
        gap: 6px;
    }

    .question-item:hover {
        background-color: #edf2f7;
    }

    .question-checkbox {
        width: 16px;
        height: 16px;
        margin-top: 2px;
        cursor: pointer;
        accent-color: #27ae60;
    }

    .question-label {
        font-weight: 600;
        color: #2c3e50;
        cursor: pointer;
        display: block;
        margin-bottom: 2px;
        font-size: 13px;
    }

    .question-meta {
        margin: 0;
        color: #7f8c8d;
        font-size: 11px;
    }

    .answers-list {
        margin: 0;
        padding-left: 18px;
        color: #555;
        font-size: 11px;
    }

    .correct-answer {
        font-weight: 600;
        color: #27ae60;
    }

    /* PDF Preview Styles - Matches actual PDF exactly */
    .pdf-preview-container {
        font-family: helvetica, Arial, sans-serif;
        width: 595px;
        /* Exact A4 width in points */
        max-width: 595px;
        margin: 0 auto;
        padding: 40px 40px 20px 40px;
        background: white;
        font-size: 11pt;
        line-height: 1.4;
        box-sizing: border-box;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
    }

    .preview-header {
        text-align: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #3498db;
    }

    .preview-school-name {
        font-size: 20pt;
        font-weight: bold;
        color: #3498db;
        /* Blue color */
        margin: 0 0 5px 0;
        font-family: helvetica, Arial, sans-serif;
    }

    .preview-title {
        font-size: 16pt;
        font-weight: bold;
        color: #34495e;
        margin: 5px 0;
        font-family: helvetica, Arial, sans-serif;
    }

    .preview-date {
        color: #7f8c8d;
        font-size: 10pt;
        margin: 0;
        text-align: right;
        font-family: helvetica, Arial, sans-serif;
    }

    .preview-info-row {
        display: flex;
        justify-content: space-between;
        margin: 10px 0;
        font-size: 11pt;
        color: #2c3e50;
        font-family: helvetica, Arial, sans-serif;
    }

    .preview-question {
        margin-bottom: 20px;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .preview-question-header {
        display: flex;
        align-items: baseline;
        gap: 5px;
        margin-bottom: 5px;
        position: relative;
    }

    .preview-question-number {
        font-size: 12pt;
        font-weight: bold;
        color: #2980b9;
        white-space: nowrap;
        font-family: helvetica, Arial, sans-serif;
    }

    .preview-question-text {
        font-size: 11pt;
        font-weight: normal;
        color: #000000;
        /* Black color for question text */
        word-wrap: break-word;
        white-space: pre-wrap;
        flex: 1;
        font-family: helvetica, Arial, sans-serif;
    }

    .preview-marks {
        background: #e74c3c;
        /* Red background */
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 9pt;
        font-weight: bold;
        margin-left: auto;
        white-space: nowrap;
        font-family: helvetica, Arial, sans-serif;
    }

    .preview-options {
        margin-top: 8px;
        margin-left: 20px;
        font-size: 10pt;
    }

    .preview-option {
        margin: 4px 0;
        padding: 2px 0;
        font-size: 10pt;
        color: #444;
        font-family: helvetica, Arial, sans-serif;
    }

    .preview-answer {
        margin-top: 8px;
        margin-left: 20px;
        padding: 0;
        font-size: 10pt;
        word-wrap: break-word;
        white-space: pre-wrap;
        font-family: helvetica, Arial, sans-serif;
        color: #2c3e50;
    }

    .preview-page-number {
        text-align: center;
        color: #999;
        font-size: 9pt;
        margin-top: 20px;
        padding-top: 10px;
        border-top: 1px dashed #ccc;
        font-family: helvetica, Arial, sans-serif;
    }

    .preview-type-separator {
        margin: 15px 0 10px 0;
        font-size: 12pt;
        font-weight: bold;
        color: #2c3e50;
        border-bottom: 1px solid #3498db;
        padding-bottom: 5px;
    }

    .pdf-attached-badge {
        background-color: #27ae60;
        color: white;
        padding: 3px 10px;
        border-radius: 15px;
        font-size: 12px;
        margin-left: 10px;
    }

    .prompt-indicator {
        font-weight: 600;
        color: #27ae60;
    }

    /* Image styles for preview */
    .preview-question-text img,
    .preview-option img,
    .preview-answer img {
        max-width: 100%;
        height: auto;
        display: block;
        margin: 5px 0;
    }

    .preview-question-text table,
    .preview-option table,
    .preview-answer table {
        max-width: 100%;
        border-collapse: collapse;
        margin: 5px 0;
    }

    .preview-question-text td,
    .preview-question-text th,
    .preview-option td,
    .preview-option th,
    .preview-answer td,
    .preview-answer th {
        border: 1px solid #ddd;
        padding: 4px;
    }
</style>

<script>
    // Helper function to decode HTML entities and render HTML properly
    function decodeAndRenderHTML(text) {
        if (!text) return '';
        // Create a temporary div to decode entities and return innerHTML
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = text;
        return tempDiv.innerHTML;
    }

    // Helper function to strip HTML for PDF text (since PDF can't render HTML)
    function stripHtmlForPDF(html) {
        if (!html) return '';
        var tmp = document.createElement('DIV');
        tmp.innerHTML = html;
        return tmp.textContent || tmp.innerText || '';
    }

    function generatePreviewHTML(selectedQuestions) {
        var title = getPaperTitle();
        var schoolName = "{{ session('school_name') ?? 'School Name' }}";
        var date = new Date().toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
        var standardName = $('#standard option:selected').text() || 'Standard';
        var subjectName = $('#subject option:selected').text() || 'Subject';

        // Split questions by type
        var mcqQuestions = selectedQuestions.filter(q => q.multiple_answer == 1);
        var narrativeQuestions = selectedQuestions.filter(q => q.multiple_answer == 0);

        var html = '<div class="pdf-preview-container">';

        // Header - exactly matching new design
        html += '<div class="preview-header">';
        html += '<div class="preview-school-name">' + schoolName + '</div>';
        html += '<div class="preview-info-row">';
        html += '<span><strong>Standard:</strong> ' + standardName + '</span>';
        html += '<span><strong>Subject:</strong> ' + subjectName + '</span>';
        html += '<span><strong>Date:</strong> ' + date + '</span>';
        html += '</div>';
        html += '</div>';

        // MCQ Section
        if (mcqQuestions.length > 0) {
            for (var i = 0; i < mcqQuestions.length; i++) {
                var q = mcqQuestions[i];
                var globalQNumber = i + 1;

                html += '<div class="preview-question">';

                // Question header with number, text and marks in one line
                html += '<div class="preview-question-header">';
                html += '<span class="preview-question-number">Q' + globalQNumber + '.</span>';
                html += '<span class="preview-question-text">' + decodeAndRenderHTML(q.question_title || 'N/A') + '</span>';
                if (q.points) {
                    html += '<span class="preview-marks">' + q.points + ' marks</span>';
                }
                html += '</div>';

                // MCQ options - directly show options
                if (q.answers && q.answers.length) {
                    html += '<div class="preview-options">';
                    for (var j = 0; j < q.answers.length; j++) {
                        var ans = q.answers[j];
                        var letter = String.fromCharCode(65 + j);
                        html += '<div class="preview-option">';
                        html += '<span style="display:inline-block; width:25px;">' + letter + '.</span>';
                        html += '<span>' + decodeAndRenderHTML(ans.answer) + '</span>';
                        html += '</div>';
                    }
                    html += '</div>';
                }

                html += '</div>';
            }
        }

        // Narrative Section
        if (narrativeQuestions.length > 0) {
            for (var i = 0; i < narrativeQuestions.length; i++) {
                var q = narrativeQuestions[i];
                var globalQNumber = mcqQuestions.length + i + 1;

                html += '<div class="preview-question">';

                // Question header with number, text and marks in one line
                html += '<div class="preview-question-header">';
                html += '<span class="preview-question-number">Q' + globalQNumber + '.</span>';
                html += '<span class="preview-question-text">' + decodeAndRenderHTML(q.question_title || 'N/A') + '</span>';
                if (q.points) {
                    html += '<span class="preview-marks">' + q.points + ' marks</span>';
                }
                html += '</div>';

                html += '</div>';
            }
        }

        if (selectedQuestions.length === 0) {
            html += '<p style="text-align:center; padding:40px; color:#7f8c8d;">No questions selected</p>';
        }

        // Page number indicator (for preview only)
        html += '<div class="preview-page-number">Page 1 of 1 (Preview)</div>';

        html += '</div>';

        return html;
    }

    // Generate PDF and attach to file input - EXACTLY MATCHING HTML PREVIEW
    function generateAndAttachPDF() {
        var selectedQuestions = getSelectedQuestionsSorted();

        if (selectedQuestions.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No Questions Selected',
                text: 'Please select at least one question to generate PDF'
            });
            return;
        }

        // Update prompt field with questions
        updatePromptBasedOnToggle();

        // Show loading message
        Swal.fire({
            title: 'Generating PDF...',
            text: 'Please wait',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Use setTimeout to ensure UI updates
        setTimeout(function() {
            try {
                // Create PDF with exact A4 dimensions
                const pdf = new jspdf.jsPDF({
                    orientation: 'portrait',
                    unit: 'pt',
                    format: 'a4'
                });

                // A4 dimensions
                const pageWidth = 595.28;
                const pageHeight = 841.89;
                const margin = 40;
                const contentWidth = pageWidth - (margin * 2);

                // School info
                const schoolName = "{{ session('school_name') ?? 'School Name' }}";
                const standardName = $('#standard option:selected').text() || 'Standard';
                const subjectName = $('#subject option:selected').text() || 'Subject';
                const date = new Date().toLocaleDateString('en-GB', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                });

                // Split questions by type
                var mcqQuestions = selectedQuestions.filter(q => q.multiple_answer == 1);
                var narrativeQuestions = selectedQuestions.filter(q => q.multiple_answer == 0);

                let currentPage = 1;
                let yPosition = margin;

                // Set initial page
                pdf.setPage(currentPage);

                // HEADER - EXACTLY MATCHING HTML PREVIEW
                // School Name - Blue color (#3498db)
                pdf.setFontSize(20);
                pdf.setFont('helvetica', 'bold');
                pdf.setTextColor(52, 152, 219); // #3498db in RGB
                pdf.text(schoolName, pageWidth / 2, yPosition, {
                    align: 'center'
                });
                yPosition += 25;

                // Info Row: Standard, Subject, Date
                pdf.setFontSize(11);
                pdf.setFont('helvetica', 'normal');
                pdf.setTextColor(44, 62, 80); // #2c3e50

                // Calculate positions for info row - exactly centered like HTML
                const infoY = yPosition;
                const colWidth = pageWidth / 3;

                pdf.text('Standard: ' + standardName, margin, infoY);
                pdf.text('Subject: ' + subjectName, pageWidth / 2, infoY, {
                    align: 'center'
                });
                pdf.text('Date: ' + date, pageWidth - margin, infoY, {
                    align: 'right'
                });

                yPosition += 15;

                // Draw blue line
                pdf.setDrawColor(52, 152, 219);
                pdf.setLineWidth(1);
                pdf.line(margin, yPosition, pageWidth - margin, yPosition);

                yPosition += 20;

                // Process MCQ questions first
                let questionCounter = 1;

                // MCQ Section
                for (let i = 0; i < mcqQuestions.length; i++) {
                    const q = mcqQuestions[i];

                    // Check if we need a new page
                    if (yPosition > pageHeight - margin - 50) {
                        pdf.addPage();
                        currentPage++;
                        pdf.setPage(currentPage);
                        yPosition = margin + 20;
                    }

                    // Question number - Blue color (#2980b9)
                    pdf.setFontSize(12);
                    pdf.setFont('helvetica', 'bold');
                    pdf.setTextColor(41, 128, 185); // #2980b9

                    const qNumber = 'Q' + questionCounter + '.';
                    pdf.text(qNumber, margin, yPosition);

                    // Question text - Black color
                    pdf.setFont('helvetica', 'normal');
                    pdf.setTextColor(0, 0, 0); // Black

                    const questionText = stripHtmlForPDF(q.question_title || 'N/A');
                    const availableWidth = contentWidth - 80; // Leave space for marks and number

                    // Split question text
                    const questionLines = pdf.splitTextToSize(questionText, availableWidth);

                    // Calculate vertical position for first line of question text
                    const textX = margin + 25; // Indent after Q number

                    // Write first line of question text
                    if (questionLines.length > 0) {
                        pdf.text(questionLines[0], textX, yPosition);
                    }

                    // Add marks - Black text, no background
                    if (q.points) {
                        const marksText = '[' + q.points + ' marks]';

                        // Set black text color
                        pdf.setTextColor(231, 76, 60); // black
                        pdf.setFontSize(10);

                        pdf.text(marksText, pageWidth - margin - 10, yPosition - 1, {
                            align: 'right'
                        });

                        // Optional: reset font size for next content
                        pdf.setFontSize(11);
                    }

                    yPosition += (questionLines.length * 14);

                    // MCQ options
                    if (q.answers && q.answers.length) {
                        for (let j = 0; j < q.answers.length; j++) {
                            const ans = q.answers[j];
                            const letter = String.fromCharCode(65 + j);

                            // Check if option will fit
                            if (yPosition > pageHeight - margin - 40) {
                                pdf.addPage();
                                currentPage++;
                                pdf.setPage(currentPage);
                                yPosition = margin + 20;
                            }

                            pdf.setFont('helvetica', 'normal');
                            pdf.setTextColor(68, 68, 68); // #444

                            const optionText = letter + '. ' + stripHtmlForPDF(ans.answer);
                            const optionLines = pdf.splitTextToSize(optionText, contentWidth - 40);

                            pdf.text(optionLines, margin + 25, yPosition);
                            yPosition += (optionLines.length * 13) + 3;
                        }
                        yPosition += 5;
                    }

                    yPosition += 10;
                    questionCounter++;
                }

                // Narrative Section
                for (let i = 0; i < narrativeQuestions.length; i++) {
                    const q = narrativeQuestions[i];

                    // Check if we need a new page
                    if (yPosition > pageHeight - margin - 50) {
                        pdf.addPage();
                        currentPage++;
                        pdf.setPage(currentPage);
                        yPosition = margin + 20;
                    }

                    // Question number - Blue color
                    pdf.setFontSize(12);
                    pdf.setFont('helvetica', 'bold');
                    pdf.setTextColor(41, 128, 185);

                    const qNumber = 'Q' + questionCounter + '.';
                    pdf.text(qNumber, margin, yPosition);

                    // Question text - Black color
                    pdf.setFont('helvetica', 'normal');
                    pdf.setTextColor(0, 0, 0);

                    const questionText = stripHtmlForPDF(q.question_title || 'N/A');
                    const availableWidth = contentWidth - 80;

                    const questionLines = pdf.splitTextToSize(questionText, availableWidth);
                    const textX = margin + 25;

                    if (questionLines.length > 0) {
                        pdf.text(questionLines[0], textX, yPosition);
                    }

                    // Add marks - Red background with white text
                                        if (q.points) {
                        const marksText = '[' + q.points + ' marks]';

                        // Set black text color
                        pdf.setTextColor(231, 76, 60); // black
                        pdf.setFontSize(10);

                        pdf.text(marksText, pageWidth - margin - 10, yPosition - 1, {
                            align: 'right'
                        });

                        // Optional: reset font size for next content
                        pdf.setFontSize(11);
                    }

                    yPosition += (questionLines.length * 14) + 10;
                    questionCounter++;
                }

                // Add page numbers to all pages
                for (let i = 1; i <= currentPage; i++) {
                    pdf.setPage(i);
                    pdf.setFontSize(10);
                    pdf.setTextColor(150, 150, 150);
                    pdf.text('Page ' + i + ' of ' + currentPage, pageWidth / 2, pageHeight - margin + 10, {
                        align: 'center'
                    });
                }

                // Convert PDF to blob
                var pdfBlob = pdf.output('blob');
                var fileName = getPaperTitle().replace(/[^a-z0-9]/gi, '-').toLowerCase() + '.pdf';

                // Create a File object from the blob
                var pdfFile = new File([pdfBlob], fileName, {
                    type: 'application/pdf'
                });

                // Create a DataTransfer object to set the file input
                var dataTransfer = new DataTransfer();
                dataTransfer.items.add(pdfFile);

                // Set the file input's files
                var fileInput = document.getElementById('image');
                fileInput.files = dataTransfer.files;

                // Update UI to show PDF is attached
                $('#pdfFileName').html('<span class="pdf-attached-badge">✓ PDF Attached: ' + fileName + '</span>');
                $('#pdfGenerated').val('1');

                // Close the modal if open
                $('#pdfPreviewModal').modal('hide');

                // Close loading message and show success
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'PDF has been generated and attached to the homework',
                    timer: 2000,
                    showConfirmButton: false
                });

            } catch (error) {
                console.error('Error generating PDF:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to generate PDF. Please try again.'
                });
            }
        }, 500);
    }
</script>