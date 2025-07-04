-- Migration: Add consultation_fee column to dentist_profiles table
-- Run this if the consultation_fee column doesn't exist in your dentist_profiles table

USE simple_republic_dental_clinic_dc;

-- Add consultation_fee column if it doesn't exist
ALTER TABLE dentist_profiles 
ADD COLUMN IF NOT EXISTS consultation_fee DECIMAL(10, 2) DEFAULT 0.00 
AFTER years_of_experience;

-- Update existing dentist profiles to have a default consultation fee if needed
-- UPDATE dentist_profiles SET consultation_fee = 100.00 WHERE consultation_fee = 0.00;
