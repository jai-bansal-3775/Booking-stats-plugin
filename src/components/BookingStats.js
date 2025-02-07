import React, { useState, useEffect } from "react";

const BookingStats = () => {
  const [days, setDays] = useState(7);
  const [count, setCount] = useState(0);

  useEffect(() => {
    fetchBookings();
  }, [days]);

  const fetchBookings = async () => {
    const response = await fetch(bookingStatsData.ajaxUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        action: "fetch_booking_stats",
        nonce: bookingStatsData.nonce,
        days,
      }),
    });
    const data = await response.json();
    setCount(data.count);
  };

  return (
    <div>
      <label>
        Show bookings from last
        <input
          type="number"
          value={days}
          onChange={(e) => setDays(e.target.value)}
          min="1"
        />
        days
      </label>
      <p>Total Bookings: {count}</p>
    </div>
  );
};

export default BookingStats;
