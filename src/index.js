import React from "react";
import { createRoot } from "react-dom/client";
import App from "./App";

const container = document.getElementById("booking-stats-root");
const root = createRoot(container);
root.render(<App />);