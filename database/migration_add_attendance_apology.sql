ALTER TABLE meeting_attendees
    MODIFY attendance_status ENUM('Present', 'Absent', 'Apology', 'Excused', 'Late') DEFAULT 'Absent';
