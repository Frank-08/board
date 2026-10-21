/**
 * Builds the agenda item report Word template offered on submit_agenda.php.
 *
 * Usage: scripts/build_report_template.sh   (needs `docx` from npm and python3)
 *
 * Writes a .docx to the path given as argv[2]; the shell wrapper then retypes
 * it as a .dotx so Word opens a fresh document from it rather than editing
 * the template itself. Edit the section list below to change the standard
 * report layout, then rebuild and commit assets/templates/.
 */
const fs = require('fs');
const {
    AlignmentType, BorderStyle, CheckBox, Document, Footer, Header, HeadingLevel,
    LevelFormat, Packer, PageNumber, Paragraph, ShadingType, Table, TableCell,
    TableRow, TextRun, WidthType
} = require('docx');

const outPath = process.argv[2];
if (!outPath) {
    console.error('Usage: node build_report_template.js <output.docx>');
    process.exit(1);
}

const FONT = 'Arial';
const ACCENT = '2C5282';
const LABEL_FILL = 'E8EEF5';
const GUIDE_FILL = 'FFF8E1';
const PLACEHOLDER = '808080';
// A4 with 2cm margins: 11906 - 2 * 1134 = 9638 DXA of usable width
const PAGE_WIDTH = 9638;
const LABEL_WIDTH = 2500;
const VALUE_WIDTH = PAGE_WIDTH - LABEL_WIDTH;

const cellBorder = { style: BorderStyle.SINGLE, size: 4, color: 'BFC8D2' };
const cellBorders = { top: cellBorder, bottom: cellBorder, left: cellBorder, right: cellBorder };
const cellMargins = { top: 80, bottom: 80, left: 120, right: 120 };

// Grey bracketed prompt text the author types over
function placeholder(text) {
    return new TextRun({ text: `[${text}]`, color: PLACEHOLDER, italics: true });
}

function para(children, options = {}) {
    return new Paragraph({ children: Array.isArray(children) ? children : [children], ...options });
}

function guidance(text) {
    return para(new TextRun({ text, italics: true, size: 18, color: '6B5B00' }), {
        shading: { type: ShadingType.CLEAR, fill: GUIDE_FILL },
        border: { left: { style: BorderStyle.SINGLE, size: 18, color: 'F0AD4E', space: 6 } },
        spacing: { after: 120 }
    });
}

function checkboxes(options) {
    const runs = [];
    options.forEach((label, i) => {
        runs.push(new CheckBox({ checked: false }));
        runs.push(new TextRun({ text: ` ${label}${i < options.length - 1 ? '    ' : ''}` }));
    });
    return runs;
}

function labelCell(text) {
    return new TableCell({
        width: { size: LABEL_WIDTH, type: WidthType.DXA },
        borders: cellBorders,
        margins: cellMargins,
        shading: { type: ShadingType.CLEAR, fill: LABEL_FILL },
        children: [para(new TextRun({ text, bold: true }))]
    });
}

function valueCell(children) {
    return new TableCell({
        width: { size: VALUE_WIDTH, type: WidthType.DXA },
        borders: cellBorders,
        margins: cellMargins,
        children: [para(children)]
    });
}

function detailsTable(rows) {
    return new Table({
        width: { size: PAGE_WIDTH, type: WidthType.DXA },
        columnWidths: [LABEL_WIDTH, VALUE_WIDTH],
        rows: rows.map(([label, value]) => new TableRow({ children: [labelCell(label), valueCell(value)] }))
    });
}

function heading(text) {
    return new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun(text)] });
}

const detailsRows = [
    ['Council / committee', placeholder('e.g. Presbytery Standing Committee')],
    ['Meeting date', placeholder('Date of the meeting this report is for')],
    ['Agenda item title', placeholder('Same title as entered on the submission form')],
    ['Report from', placeholder('Name, role / committee or ministry area')],
    ['Presenter(s)', placeholder('Who will speak to this item at the meeting')],
    ['Contact', placeholder('Email and phone for questions before the meeting')],
    ['Decision sought', checkboxes(['Consensus', 'Formal majority', 'Referral', 'For information'])],
    ['Time requested', placeholder('Minutes, including discussion')],
    ['Confidentiality', checkboxes(['Open', 'Confidential (in committee)'])],
    ['Date submitted', placeholder('Date')]
];

const implicationsRows = [
    ['Financial', 'Cost, budget line, funding source, ongoing costs.'],
    ['Legal & Regulations', 'Constitution and Regulations, legislation, contracts, property trust.'],
    ['Risk', 'Key risks of acting and of not acting, and how they are managed.'],
    ['Safe Church', 'Child safety and Safe Church implications, or "None identified".'],
    ['People', 'Ministers, staff, volunteers, workload.'],
    ['Mission & theology', 'How this serves the mission of the Church; theological considerations.']
].map(([label, prompt]) => [label, placeholder(prompt)]);

const doc = new Document({
    creator: 'Together in Council',
    title: 'Agenda Item Report',
    description: 'Standard report template for agenda item submissions',
    styles: {
        default: { document: { run: { font: FONT, size: 22 } } },
        paragraphStyles: [
            {
                id: 'Title', name: 'Title', basedOn: 'Normal', next: 'Normal', quickFormat: true,
                run: { size: 40, bold: true, color: ACCENT, font: FONT },
                paragraph: { spacing: { after: 60 } }
            },
            {
                // Page number fields take their size from the paragraph style, not the run
                id: 'Footer', name: 'footer', basedOn: 'Normal',
                run: { size: 16, color: '666666', font: FONT }
            },
            {
                id: 'Heading1', name: 'Heading 1', basedOn: 'Normal', next: 'Normal', quickFormat: true,
                run: { size: 26, bold: true, color: ACCENT, font: FONT },
                paragraph: {
                    spacing: { before: 300, after: 120 },
                    outlineLevel: 0,
                    border: { bottom: { style: BorderStyle.SINGLE, size: 6, color: 'BFC8D2', space: 2 } }
                }
            }
        ]
    },
    numbering: {
        config: [
            {
                reference: 'proposal',
                levels: [{
                    level: 0, format: LevelFormat.DECIMAL, text: '%1.', alignment: AlignmentType.LEFT,
                    style: { paragraph: { indent: { left: 720, hanging: 360 } } }
                }]
            },
            {
                reference: 'bullets',
                levels: [{
                    level: 0, format: LevelFormat.BULLET, text: '•', alignment: AlignmentType.LEFT,
                    style: { paragraph: { indent: { left: 720, hanging: 360 } } }
                }]
            }
        ]
    },
    sections: [{
        properties: {
            page: {
                size: { width: 11906, height: 16838 },
                margin: { top: 1134, bottom: 1134, left: 1134, right: 1134 }
            }
        },
        headers: {
            default: new Header({
                children: [para([
                    new TextRun({ text: 'Together in Council', bold: true, color: ACCENT, size: 18 }),
                    new TextRun({ text: '  ·  Agenda Item Report', color: '666666', size: 18 })
                ], { alignment: AlignmentType.RIGHT })]
            })
        },
        footers: {
            default: new Footer({
                children: [para(
                    new TextRun({ children: ['Page ', PageNumber.CURRENT, ' of ', PageNumber.TOTAL_PAGES] }),
                    { style: 'Footer', alignment: AlignmentType.CENTER }
                )]
            })
        },
        children: [
            new Paragraph({ style: 'Title', children: [placeholder('Agenda Item Title')] }),
            para(new TextRun({ text: 'Report to council', color: '666666' }), { spacing: { after: 200 } }),

            guidance('How to use this template: replace every [grey prompt] and delete these yellow guidance notes. '
                + 'Aim for no more than two pages plus attachments. Save the finished report to SharePoint, copy its link, '
                + 'and submit it through Together in Council → Submit Item before the agenda closes.'),

            detailsTable(detailsRows),

            heading('1. Purpose'),
            guidance('One or two sentences: what is this item about and what do you want the council to do with it?'),
            para(placeholder('This report seeks the council\'s agreement to ... / This report informs the council of ...')),

            heading('2. Proposal'),
            guidance('Write the proposal exactly as it should appear in the minutes. Begin "That the [council]". '
                + 'Use numbered clauses for a proposal with several parts. Leave this section as "For information" if no decision is sought.'),
            para([new TextRun('That the '), placeholder('council / committee name'), new TextRun(':')]),
            para(placeholder('receives / approves / endorses / refers ...'), { numbering: { reference: 'proposal', level: 0 } }),
            para(placeholder('second clause, if needed'), { numbering: { reference: 'proposal', level: 0 } }),

            heading('3. Background'),
            guidance('Only the history the council needs to understand the proposal: previous decisions (with minute numbers), how this arose.'),
            para(placeholder('Background')),

            heading('4. Discussion'),
            guidance('The key points, the options considered, and why the proposal is recommended. Keep it brief; detail belongs in attachments.'),
            para(placeholder('Discussion')),

            heading('5. Implications'),
            guidance('Write "None identified" rather than deleting a row, so readers know it was considered.'),
            detailsTable(implicationsRows),

            heading('6. Consultation'),
            guidance('Who has been consulted (committees, congregations, staff, First Peoples or CALD communities, others) and what they said.'),
            para(placeholder('Person / group – outcome of consultation'), { numbering: { reference: 'bullets', level: 0 } }),

            heading('7. Implementation and next steps'),
            guidance('If the proposal is agreed: what happens next, who is responsible, and by when.'),
            para(placeholder('Action – responsible person – timeframe'), { numbering: { reference: 'bullets', level: 0 } }),

            heading('8. Attachments'),
            guidance('List each attachment. Save attachments to the same SharePoint folder as this report so one link covers them all.'),
            para(placeholder('Attachment A – title'), { numbering: { reference: 'bullets', level: 0 } })
        ]
    }]
});

Packer.toBuffer(doc).then(buffer => {
    fs.writeFileSync(outPath, buffer);
    console.log(`Wrote ${outPath}`);
});
