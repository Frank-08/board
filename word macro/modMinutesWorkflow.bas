Attribute VB_Name = "modMinutesWorkflow"
Option Explicit

' ------------------------------------------------------------------
' Run once (or once per machine, if this template is reinstalled).
' Creates the "Resolution" paragraph style and binds it to Ctrl+Alt+R,
' both saved into Normal.dotm so they're available in every document,
' not just ones built by StartMinutes.
' ------------------------------------------------------------------
Public Sub SetupResolutionStyle()
    Dim resStyle As Style
    On Error Resume Next
    Set resStyle = NormalTemplate.Styles("Resolution")
    On Error GoTo 0

    If resStyle Is Nothing Then
        Set resStyle = NormalTemplate.Styles.Add(Name:="Resolution", Type:=wdStyleTypeParagraph)
    End If

    With resStyle.Font
        .Bold = True
    End With
    With resStyle.ParagraphFormat
        .LeftIndent = InchesToPoints(0.3)
        .SpaceBefore = 6
        .SpaceAfter = 6
    End With
    resStyle.NextParagraphStyle = NormalTemplate.Styles(wdStyleNormal)

    CustomizationContext = NormalTemplate
    KeyBindings.Add KeyCategory:=wdKeyCategoryStyle, _
                    Command:="Resolution", _
                    KeyCode:=BuildKeyCode(wdKeyControl, wdKeyAlt, wdKeyR)
    NormalTemplate.Save

    MsgBox "Resolution style created and bound to Ctrl+Alt+R." & vbCrLf & _
           "If the shortcut doesn't take on your Word version, bind it manually via " & _
           "Word Options > Customize Ribbon > Keyboard shortcuts > Styles > Resolution.", _
           vbInformation
End Sub

' ------------------------------------------------------------------
' Pulls the agenda for a board meeting and builds a new document with
' one Heading 2 per item (numbered as board numbers them), each
' tagged with a bookmark carrying the agenda_item_id. Free-type under
' each heading; Ctrl+Alt+R for a resolution line.
' ------------------------------------------------------------------
Public Sub StartMinutes()
    Dim meetingID As String
    meetingID = InputBox("Board meeting ID (from the meeting's URL in board):", "Start Minutes")
    If meetingID = "" Then Exit Sub

    Dim raw As String
    On Error GoTo APIFail
    raw = BoardGet("agenda.php?meeting_id=" & meetingID)
    On Error GoTo 0

    Dim items As Collection
    Set items = JsonSplitObjects(raw)
    If items.Count = 0 Then
        MsgBox "No agenda items came back for meeting " & meetingID & _
               " - check the meeting ID and that the agenda's been built in board.", _
               vbExclamation
        Exit Sub
    End If

    Dim doc As Document
    Set doc = Documents.Add   ' based on Normal.dotm, so Resolution style is available

    Dim obj As Variant
    Dim itemID As String, itemNumber As String, itemTitle As String
    Dim headingPara As Paragraph
    Dim isFirst As Boolean
    isFirst = True

    For Each obj In items
        itemID = JsonField(CStr(obj), FIELD_ITEM_ID)
        itemNumber = JsonField(CStr(obj), FIELD_ITEM_NUMBER)
        itemTitle = JsonField(CStr(obj), FIELD_ITEM_TITLE)

        If isFirst Then
            Set headingPara = doc.Paragraphs(1)
            isFirst = False
        Else
            doc.Content.InsertParagraphAfter
            Set headingPara = doc.Paragraphs.Last
        End If

        headingPara.Range.Text = itemNumber & "  " & itemTitle
        headingPara.Range.Style = doc.Styles(wdStyleHeading2)
        doc.Bookmarks.Add Name:=BOOKMARK_PREFIX & itemID, Range:=headingPara.Range

        ' Blank line under the heading, ready to free-type into.
        doc.Content.InsertParagraphAfter
        doc.Paragraphs.Last.Range.Style = doc.Styles(wdStyleNormal)
    Next obj

    On Error Resume Next
    doc.Variables("BoardMeetingID") = meetingID
    If Err.Number <> 0 Then doc.Variables.Add Name:="BoardMeetingID", Value:=meetingID
    On Error GoTo 0

    MsgBox items.Count & " agenda item(s) pulled in. Free-type under each heading, " & _
           "Ctrl+Alt+R for a resolution.", vbInformation
    Exit Sub

APIFail:
    MsgBox "Couldn't reach board: " & Err.Description, vbCritical
End Sub

' ------------------------------------------------------------------
' Walks the agenda-item bookmarks in the active document and pushes
' everything typed under each heading back to board: plain paragraphs
' become a minutes comment on that item, Resolution-styled paragraphs
' become resolutions. Safe to run any time you're back online - does
' not need to happen right after the meeting.
' ------------------------------------------------------------------
Public Sub SyncToBoard()
    Dim doc As Document
    Set doc = ActiveDocument

    Dim meetingID As String
    On Error Resume Next
    meetingID = doc.Variables("BoardMeetingID")
    On Error GoTo 0
    If meetingID = "" Then
        meetingID = InputBox("Board meeting ID for this document:", "Sync to Board")
        If meetingID = "" Then Exit Sub
    End If

    Dim alreadySynced As String
    On Error Resume Next
    alreadySynced = doc.Variables("SyncedAt")
    On Error GoTo 0
    If alreadySynced <> "" Then
        If MsgBox("This document was already synced at " & alreadySynced & _
                  "." & vbCrLf & "Sync again? This will add duplicate entries in board.", _
                  vbYesNo + vbExclamation) = vbNo Then Exit Sub
    End If

    ' Collect agenda-item bookmarks in document order.
    Dim n As Long
    n = 0
    Dim bmNames(1 To 200) As String
    Dim bmPositions(1 To 200) As Long
    Dim bm As Bookmark
    For Each bm In doc.Bookmarks
        If Left(bm.Name, Len(BOOKMARK_PREFIX)) = BOOKMARK_PREFIX Then
            n = n + 1
            bmNames(n) = bm.Name
            bmPositions(n) = bm.Range.Start
        End If
    Next bm

    If n = 0 Then
        MsgBox "No agenda-item headings found - did this document come from Start Minutes?", _
               vbExclamation
        Exit Sub
    End If

    ' Sort by position (n is small, so a simple bubble sort is fine).
    Dim i As Long, j As Long, tmpS As String, tmpL As Long
    For i = 1 To n - 1
        For j = 1 To n - i
            If bmPositions(j) > bmPositions(j + 1) Then
                tmpL = bmPositions(j): bmPositions(j) = bmPositions(j + 1): bmPositions(j + 1) = tmpL
                tmpS = bmNames(j): bmNames(j) = bmNames(j + 1): bmNames(j + 1) = tmpS
            End If
        Next j
    Next i

    Dim itemID As String, startPos As Long, endPos As Long
    Dim itemRange As Range, para As Paragraph
    Dim minutesText As String, pText As String
    Dim postedCount As Long, resolutionCount As Long, failCount As Long
    Dim errLog As String

    For i = 1 To n
        itemID = Mid(bmNames(i), Len(BOOKMARK_PREFIX) + 1)
        startPos = doc.Bookmarks(bmNames(i)).Range.End
        If i < n Then
            endPos = doc.Bookmarks(bmNames(i + 1)).Range.Start
        Else
            endPos = doc.Content.End
        End If
        If endPos <= startPos Then GoTo NextItem

        Set itemRange = doc.Range(startPos, endPos)
        minutesText = ""

        For Each para In itemRange.Paragraphs
            pText = Trim(Replace(para.Range.Text, Chr(13), ""))
            If Len(pText) = 0 Then GoTo NextPara

            If para.Range.Style = "Resolution" Then
                If TryBoardPost("resolutions.php", _
                        "{""meeting_id"":" & meetingID & _
                        ",""agenda_item_id"":" & itemID & _
                        ",""title"":""" & JsonEscape(ResolutionTitle(pText)) & """" & _
                        ",""description"":""" & JsonEscape(pText) & """}", errLog) Then
                    resolutionCount = resolutionCount + 1
                Else
                    failCount = failCount + 1
                End If
            Else
                If minutesText <> "" Then minutesText = minutesText & vbLf
                minutesText = minutesText & pText
            End If
NextPara:
        Next para

        If minutesText <> "" Then
            If TryBoardPost("minutes_comments.php", _
                    "{""meeting_id"":" & meetingID & _
                    ",""agenda_item_id"":" & itemID & _
                    ",""comment"":""" & JsonEscape(minutesText) & """}", errLog) Then
                postedCount = postedCount + 1
            Else
                failCount = failCount + 1
            End If
        End If
NextItem:
    Next i

    On Error Resume Next
    doc.Variables("SyncedAt") = Now()
    If Err.Number <> 0 Then doc.Variables.Add Name:="SyncedAt", Value:=CStr(Now())
    On Error GoTo 0

    Dim summary As String
    summary = "Synced: " & postedCount & " item comment(s), " & resolutionCount & " resolution(s)."
    If failCount > 0 Then
        summary = summary & vbCrLf & failCount & " failed - re-running will retry everything, " & _
                  "including what already succeeded." & vbCrLf & errLog
        MsgBox summary, vbExclamation
    Else
        MsgBox summary, vbInformation
    End If
End Sub

' board's resolutions table requires both a title (VARCHAR 255) and a
' description (TEXT) - Resolution-styled lines in Word are one free-typed
' block with neither split out, so this derives a title by truncating.
' The full text always goes to description untouched.
Private Function ResolutionTitle(ByVal pText As String) As String
    Const maxLen As Long = 250
    If Len(pText) <= maxLen Then
        ResolutionTitle = pText
    Else
        ResolutionTitle = Left(pText, maxLen - 3) & "..."
    End If
End Function

' Wraps BoardPost so one failed item doesn't abort the whole sync.
Private Function TryBoardPost(ByVal endpoint As String, ByVal jsonBody As String, _
                               ByRef errLog As String) As Boolean
    On Error GoTo Fail
    BoardPost endpoint, jsonBody
    TryBoardPost = True
    Exit Function
Fail:
    errLog = errLog & vbCrLf & endpoint & ": " & Err.Description
    TryBoardPost = False
End Function
