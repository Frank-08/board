Attribute VB_Name = "modBoardAPI"
Option Explicit

' Performs a GET request against the board API and returns the raw
' response body as a string.
Public Function BoardGet(ByVal endpoint As String) As String
    Dim http As Object
    Set http = CreateObject("WinHttp.WinHttpRequest.5.1")
    http.Open "GET", API_BASE_URL & endpoint, False
    http.SetRequestHeader "X-API-Key", API_KEY
    http.Send
    If http.Status <> 200 Then
        Err.Raise vbObjectError + 1, "BoardGet", _
            "Board API GET failed (" & http.Status & "): " & http.responseText
    End If
    BoardGet = http.responseText
End Function

' Performs a POST request with a JSON body against the board API.
' Raises an error on non-2xx status so callers can catch failures.
Public Function BoardPost(ByVal endpoint As String, ByVal jsonBody As String) As String
    Dim http As Object
    Set http = CreateObject("WinHttp.WinHttpRequest.5.1")
    http.Open "POST", API_BASE_URL & endpoint, False
    http.SetRequestHeader "X-API-Key", API_KEY
    http.SetRequestHeader "Content-Type", "application/json"
    http.Send jsonBody
    If http.Status <> 200 And http.Status <> 201 Then
        Err.Raise vbObjectError + 2, "BoardPost", _
            "Board API POST failed (" & http.Status & "): " & http.responseText
    End If
    BoardPost = http.responseText
End Function

' Escapes a string for safe embedding as a JSON string value.
Public Function JsonEscape(ByVal s As String) As String
    s = Replace(s, "\", "\\")
    s = Replace(s, """", "\""")
    s = Replace(s, vbCrLf, "\n")
    s = Replace(s, vbCr, "\n")
    s = Replace(s, vbLf, "\n")
    s = Replace(s, vbTab, "\t")
    JsonEscape = s
End Function

' Splits a JSON array of flat objects ("[{...},{...}]") into a
' Collection of the individual object substrings (braces included).
' This is purpose-built for board's agenda.php response shape - it is
' NOT a general JSON parser and will not handle nested objects/arrays
' inside an item.
Public Function JsonSplitObjects(ByVal jsonArray As String) As Collection
    Dim result As New Collection
    Dim depth As Long, startPos As Long, i As Long, c As String
    depth = 0: startPos = 0
    For i = 1 To Len(jsonArray)
        c = Mid(jsonArray, i, 1)
        If c = "{" Then
            If depth = 0 Then startPos = i
            depth = depth + 1
        ElseIf c = "}" Then
            depth = depth - 1
            If depth = 0 And startPos > 0 Then
                result.Add Mid(jsonArray, startPos, i - startPos + 1)
                startPos = 0
            End If
        End If
    Next i
    Set JsonSplitObjects = result
End Function

' Extracts a single field's value from a flat JSON object string.
' Handles "key":"string value" and "key":123 forms only.
' Known limitation: a genuinely empty string value ("") is not
' distinguished from a missing field - both return "".
Public Function JsonField(ByVal jsonObject As String, ByVal fieldName As String) As String
    Dim re As Object
    Set re = CreateObject("VBScript.RegExp")
    re.Global = False
    re.IgnoreCase = True
    re.Pattern = """" & fieldName & """\s*:\s*(""((?:[^""\\]|\\.)*)""|[-0-9.]+)"

    If re.Test(jsonObject) Then
        Dim m As Object
        Set m = re.Execute(jsonObject)(0)
        If Len(m.SubMatches(1)) > 0 Then
            JsonField = Replace(m.SubMatches(1), "\""", """")
        Else
            JsonField = m.SubMatches(0)
        End If
    Else
        JsonField = ""
    End If
End Function
